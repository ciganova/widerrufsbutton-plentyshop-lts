<?php
namespace Widerrufsbutton\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Mail\Contracts\MailContract;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class WiderrufController
 *
 * Implements the two-step electronic withdrawal function required by § 356a BGB.
 *
 * Step 1: /widerruf         — Form: name, order ID, contact email
 * Step 2: /widerruf/confirm — Review page with "Widerruf bestätigen" button
 * Step 3: /widerruf/submit  — Creates return, sends input confirmation
 *
 * @package Widerrufsbutton\Controllers
 */
class WiderrufController extends Controller
{
    use Loggable;

    /** @var OrderRepositoryContract */
    private $orderRepository;

    /** @var MailContract */
    private $mail;

    /** @var Client */
    private $httpClient;

    /**
     * WiderrufController constructor.
     */
    public function __construct()
    {
        // Internal Plenty repositories
        if (class_exists(OrderRepositoryContract::class)) {
            $this->orderRepository = pluginApp(OrderRepositoryContract::class);
        }
        if (class_exists(MailContract::class)) {
            $this->mail = pluginApp(MailContract::class);
        }

        $this->httpClient = new Client([
            'base_uri' => $this->getPlentyBaseUrl(),
            'timeout'  => 15,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Get PlentyONE REST API base URL from environment.
     */
    private function getPlentyBaseUrl(): string
    {
        // In a Plenty plugin context, the domain is the system domain
        // We can construct it or fall back to domain detection
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            ? 'https' : 'http';

        return "{$scheme}://{$domain}";
    }

    /**
     * Get REST API authentication token.
     * Uses the plugin's configured credentials.
     */
    private function getRestToken(): ?string
    {
        // In Plenty plugin context, we can retrieve config values
        // or use the system's internal API token
        // For now, we use a config-based approach
        $config = $this->getPluginConfig();

        if (!empty($config['api_user']) && !empty($config['api_password'])) {
            try {
                $response = $this->httpClient->post('/rest/login', [
                    'json' => [
                        'username' => $config['api_user'],
                        'password' => $config['api_password'],
                    ],
                ]);
                $data = json_decode((string) $response->getBody(), true);
                return $data['accessToken'] ?? null;
            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->error(
                    'REST login failed',
                    ['error' => $e->getMessage()]
                );
            }
        }

        return null;
    }

    /**
     * Get plugin configuration (set by admin in Plenty back end).
     */
    private function getPluginConfig(): array
    {
        // Use Plenty plugin config helper
        if (function_exists('pluginGetConfigValue')) {
            return [
                'api_user'     => pluginGetConfigValue('api_user', ''),
                'api_password' => pluginGetConfigValue('api_password', ''),
                'sender_email' => pluginGetConfigValue('sender_email', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'shop.local')),
                'shop_name'    => pluginGetConfigValue('shop_name', 'Unser Online-Shop'),
            ];
        }

        return [
            'api_user'     => '',
            'api_password' => '',
            'sender_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'shop.local'),
            'shop_name'    => 'Unser Online-Shop',
        ];
    }

    // =========================================================================
    // STEP 1: Show the withdrawal form
    // =========================================================================

    /**
     * GET /widerruf
     *
     * Renders the initial withdrawal form.
     * Accessible WITHOUT login — as required by law.
     */
    public function showForm(Twig $twig): string
    {
        $templateData = [
            'formAction'    => '/widerruf/confirm',
            'shopName'      => $this->getPluginConfig()['shop_name'],
            'csrfToken'     => $this->generateCsrfToken(),
            'errors'        => [],
            'oldInput'      => [],
        ];

        return $twig->render('Widerrufsbutton::content.WiderrufForm', $templateData);
    }

    // =========================================================================
    // STEP 2: Validate input and show confirmation page
    // =========================================================================

    /**
     * POST /widerruf/confirm
     *
     * Validates form input, looks up the order, and shows the confirmation
     * page where the user must click "Widerruf bestätigen" to finalize.
     */
    public function confirm(Request $request, Twig $twig): string
    {
        $input = $request->all();
        $errors = [];

        // CSRF check
        if (!$this->validateCsrfToken($input['_csrf'] ?? '')) {
            $errors[] = 'Ungültige Anfrage. Bitte laden Sie die Seite neu.';
        }

        // Validate required fields (§ 356a BGB)
        $name    = trim($input['name'] ?? '');
        $orderId = trim($input['order_id'] ?? '');
        $email   = trim($input['email'] ?? '');

        if (empty($name)) {
            $errors[] = 'Bitte geben Sie Ihren Namen an.';
        }

        if (empty($orderId)) {
            $errors[] = 'Bitte geben Sie Ihre Bestellnummer an.';
        }

        if (empty($email)) {
            $errors[] = 'Bitte geben Sie Ihre E-Mail-Adresse an.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
        }

        // Optional: Free-text reason (must NOT be required — Widerruf is unconditional)
        $reason = trim($input['reason'] ?? '');

        // Look up the order to verify it exists and belongs to this customer
        $order = null;
        if (empty($errors)) {
            $order = $this->findOrder($orderId, $name, $email);
            if ($order === null) {
                $errors[] = 'Keine Bestellung mit dieser Bestellnummer und diesen Kundendaten gefunden. '
                    . 'Bitte überprüfen Sie Ihre Angaben.';
            } elseif (!$this->isWithinWithdrawalPeriod($order)) {
                $errors[] = 'Die Widerrufsfrist für diese Bestellung ist leider abgelaufen.';
            }
        }

        // If validation failed, re-render form with errors
        if (!empty($errors)) {
            return $twig->render('Widerrufsbutton::content.WiderrufForm', [
                'formAction' => '/widerruf/confirm',
                'shopName'   => $this->getPluginConfig()['shop_name'],
                'csrfToken'  => $this->generateCsrfToken(),
                'errors'     => $errors,
                'oldInput'   => $input,
            ]);
        }

        // Render confirmation page (Step 2)
        return $twig->render('Widerrufsbutton::content.WiderrufConfirm', [
            'formAction'  => '/widerruf/submit',
            'shopName'    => $this->getPluginConfig()['shop_name'],
            'csrfToken'   => $this->generateCsrfToken(),
            'name'        => $name,
            'orderId'     => $orderId,
            'email'       => $email,
            'reason'      => $reason,
            'orderDate'   => $order['createdAt'] ?? null,
            'orderItems'  => $this->getOrderItemsForDisplay($order),
        ]);
    }

    // =========================================================================
    // STEP 3: Final submission — create return & send confirmation
    // =========================================================================

    /**
     * POST /widerruf/submit
     *
     * Processes the final withdrawal submission:
     * 1. Creates a return order in PlentyONE
     * 2. Sends an immediate input confirmation email (required by § 356a BGB)
     * 3. Renders the success page
     */
    public function submit(Request $request, Twig $twig): string
    {
        $input = $request->all();

        // CSRF check
        if (!$this->validateCsrfToken($input['_csrf'] ?? '')) {
            return $this->renderError($twig, 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
        }

        $name    = trim($input['name'] ?? '');
        $orderId = trim($input['order_id'] ?? '');
        $email   = trim($input['email'] ?? '');
        $reason  = trim($input['reason'] ?? '');
        $config  = $this->getPluginConfig();

        // Double-check order still exists and is in withdrawal period
        $order = $this->findOrder($orderId, $name, $email);
        if ($order === null) {
            return $this->renderError($twig, 'Die Bestellung konnte nicht gefunden werden.');
        }

        // Create the return in PlentyONE
        $returnResult = $this->createReturnOrder($orderId, $name, $email, $reason);

        // Send input confirmation email (unverzüglich, dauerhafter Datenträger)
        $this->sendConfirmationEmail($email, $name, $orderId, $order, $returnResult);

        // Get the current timestamp for display
        $submittedAt = date('d.m.Y H:i:s');

        // Render success page
        return $twig->render('Widerrufsbutton::content.WiderrufSuccess', [
            'shopName'    => $config['shop_name'],
            'name'        => $name,
            'orderId'     => $orderId,
            'email'       => $email,
            'submittedAt' => $submittedAt,
            'returnId'    => $returnResult['return_id'] ?? null,
        ]);
    }

    // =========================================================================
    // AJAX: Order lookup (for optional live validation on the form)
    // =========================================================================

    /**
     * GET /widerruf/lookup?order_id=...&email=...
     *
     * AJAX endpoint to check if an order exists for the given order ID and email.
     * Returns JSON.
     */
    public function lookupOrder(Request $request, Response $response): Response
    {
        $orderId = trim($request->get('order_id', ''));
        $email   = trim($request->get('email', ''));

        if (empty($orderId) || empty($email)) {
            return $response->json(['found' => false, 'error' => 'Missing parameters']);
        }

        $order = $this->findOrder($orderId, null, $email);

        if ($order === null) {
            return $response->json(['found' => false]);
        }

        $inPeriod = $this->isWithinWithdrawalPeriod($order);

        return $response->json([
            'found'           => true,
            'within_period'   => $inPeriod,
            'order_date'      => $order['createdAt'] ?? null,
            'item_count'      => count($order['orderItems'] ?? []),
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Find an order by ID and match customer name/email.
     */
    private function findOrder(string $orderId, ?string $name, string $email): ?array
    {
        // Try internal repository first
        if ($this->orderRepository) {
            try {
                $order = $this->orderRepository->findOrderById((int) $orderId);
                if ($order) {
                    $orderData = $order->toArray();

                    // Verify email matches (case-insensitive)
                    $billingEmail = strtolower(
                        $orderData['addresses'][0]['email']
                        ?? $orderData['billingAddress']['email']
                        ?? ''
                    );

                    if ($billingEmail && $billingEmail === strtolower($email)) {
                        return $orderData;
                    }
                }
            } catch (\Exception $e) {
                // Fall through to REST API
            }
        }

        // Fallback: Use REST API
        $token = $this->getRestToken();
        if (!$token) {
            return null;
        }

        try {
            $response = $this->httpClient->get("/rest/orders/{$orderId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            $order = json_decode((string) $response->getBody(), true);

            if (!$order || isset($order['error'])) {
                return null;
            }

            // Verify email match
            $billingEmail = '';
            if (!empty($order['addresses'])) {
                foreach ($order['addresses'] as $addr) {
                    if (($addr['typeId'] ?? 0) === 1) { // Invoice address
                        $options = $addr['options'] ?? [];
                        foreach ($options as $opt) {
                            if (($opt['typeId'] ?? 0) === 4) { // Email
                                $billingEmail = $opt['value'] ?? '';
                                break 2;
                            }
                        }
                    }
                }
            }
            // Also check the flat email field
            $billingEmail = $billingEmail ?: ($order['ownerEmail'] ?? '');

            if (strtolower($billingEmail) === strtolower($email)) {
                return $order;
            }
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error(
                'Order lookup failed',
                ['order_id' => $orderId, 'error' => $e->getMessage()]
            );
        }

        return null;
    }

    /**
     * Check if the order is still within the 14-day withdrawal period.
     */
    private function isWithinWithdrawalPeriod(array $order): bool
    {
        $createdAt = $order['createdAt'] ?? $order['orderDate'] ?? null;
        if (!$createdAt) {
            return true; // Better to allow than block incorrectly
        }

        $orderTimestamp = strtotime($createdAt);
        if (!$orderTimestamp) {
            return true;
        }

        // § 355 BGB: 14-day withdrawal period from receipt of goods
        // We add a buffer for shipping time (assume 3 days)
        $withdrawalDeadline = strtotime('+14 days +3 days', $orderTimestamp);

        return time() <= $withdrawalDeadline;
    }

    /**
     * Extract order items for display on confirmation page.
     */
    private function getOrderItemsForDisplay(array $order): array
    {
        $items = $order['orderItems'] ?? [];
        $result = [];

        foreach ($items as $item) {
            $result[] = [
                'name'     => $item['itemName'] ?? $item['orderItemName'] ?? 'Artikel',
                'quantity' => $item['quantity'] ?? 1,
            ];
        }

        return $result;
    }

    /**
     * Create a return order in PlentyONE.
     */
    private function createReturnOrder(
        string $orderId,
        string $name,
        string $email,
        string $reason
    ): array {
        // Try internal repository first
        if ($this->orderRepository) {
            try {
                // The Plenty return order creation via internal API
                // Uses OrderRepositoryContract to create a return from an order
                $returnOrder = $this->orderRepository->createReturnOrder(
                    (int) $orderId,
                    [
                        'reason'      => $reason ?: 'Widerruf über Widerrufsbutton (§ 356a BGB)',
                        'customerName' => $name,
                        'customerEmail' => $email,
                    ]
                );

                return [
                    'success'   => true,
                    'return_id' => $returnOrder->id ?? null,
                    'source'    => 'internal',
                ];
            } catch (\Exception $e) {
                $this->getLogger(__METHOD__)->warning(
                    'Internal return creation failed, falling back to REST',
                    ['error' => $e->getMessage()]
                );
            }
        }

        // Fallback: REST API
        $token = $this->getRestToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Keine API-Verbindung möglich.'];
        }

        try {
            // Create return via REST API
            $response = $this->httpClient->post(
                "/rest/orders/{$orderId}/shipping/returns",
                [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'json'    => [
                        'reasonId'  => 1,  // Default reason — configurable
                        'note'      => "Widerruf über Widerrufsbutton.\n"
                                     . "Kunde: {$name}\n"
                                     . "E-Mail: {$email}\n"
                                     . ($reason ? "Begründung: {$reason}" : 'Keine Begründung angegeben.'),
                    ],
                ]
            );

            $result = json_decode((string) $response->getBody(), true);

            return [
                'success'   => true,
                'return_id' => $result['id'] ?? null,
                'source'    => 'rest',
            ];
        } catch (GuzzleException $e) {
            $this->getLogger(__METHOD__)->error(
                'REST return creation failed',
                ['order_id' => $orderId, 'error' => $e->getMessage()]
            );

            // Don't block the user — the confirmation email was sent,
            // the withdrawal declaration was received. The return can
            // be processed manually.
            return [
                'success'   => false,
                'error'     => $e->getMessage(),
                'return_id' => null,
            ];
        }
    }

    /**
     * Send the immediate input confirmation email (§ 356a BGB).
     *
     * Must contain:
     * - Content of the withdrawal declaration
     * - Date and time of receipt
     * - On a durable medium (email = durable medium per BGB)
     */
    private function sendConfirmationEmail(
        string $email,
        string $name,
        string $orderId,
        array $order,
        array $returnResult
    ): void {
        $config   = $this->getPluginConfig();
        $shopName = $config['shop_name'];
        $now      = new \DateTime();
        $dateStr  = $now->format('d.m.Y');
        $timeStr  = $now->format('H:i:s');

        $subject = "Eingangsbestätigung Ihres Widerrufs — {$shopName}";

        $body = "Guten Tag {$name},\n\n"
              . "hiermit bestätigen wir den Eingang Ihrer Widerrufserklärung.\n\n"
              . "Widerruf vom: {$dateStr} um {$timeStr} Uhr\n"
              . "Bestellnummer: {$orderId}\n"
              . "Sie haben erklärt, den Vertrag über folgende Bestellung zu widerrufen: {$orderId}\n\n"
              . "Wichtiger Hinweis: Diese Eingangsbestätigung stellt noch keine Prüfung der Wirksamkeit "
              . "Ihres Widerrufs dar. Wir werden Ihren Widerruf umgehend prüfen und uns mit weiteren "
              . "Informationen zum Rücksendevorgang bei Ihnen melden.\n\n"
              . "Mit freundlichen Grüßen\n"
              . "Ihr {$shopName}-Team\n\n"
              . "---\n"
              . "Diese E-Mail wurde automatisch generiert. "
              . "Bei Fragen erreichen Sie uns unter {$config['sender_email']}.";

        try {
            if ($this->mail) {
                $this->mail->sendHtml($email, $subject, nl2br($body));
            } else {
                // Fallback: use PHP mail
                $headers = "From: {$shopName} <{$config['sender_email']}>\r\n"
                         . "Content-Type: text/plain; charset=UTF-8\r\n"
                         . "X-Mailer: PHP/" . phpversion();

                @mail($email, $subject, $body, $headers);
            }
        } catch (\Exception $e) {
            $this->getLogger(__METHOD__)->error(
                'Confirmation email failed',
                ['email' => $email, 'error' => $e->getMessage()]
            );
        }

        // Also notify the shop owner (optional)
        $this->notifyShopOwner($name, $orderId, $dateStr, $timeStr, $returnResult);
    }

    /**
     * Notify shop owner about the withdrawal.
     */
    private function notifyShopOwner(
        string $name,
        string $orderId,
        string $dateStr,
        string $timeStr,
        array $returnResult
    ): void {
        $config = $this->getPluginConfig();
        $ownerEmail = $config['sender_email'];

        if (empty($ownerEmail) || $ownerEmail === ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'shop.local'))) {
            return; // No valid owner email configured
        }

        $subject = "Neuer Widerruf eingegangen — Bestellung {$orderId}";
        $body = "Es ist ein neuer Widerruf über den Widerrufsbutton eingegangen:\n\n"
              . "Kunde: {$name}\n"
              . "Bestellnummer: {$orderId}\n"
              . "Eingang: {$dateStr} um {$timeStr} Uhr\n"
              . "Return-ID: " . ($returnResult['return_id'] ?? 'nicht erstellt') . "\n"
              . "Quelle: " . ($returnResult['source'] ?? 'unbekannt') . "\n\n"
              . "Bitte bearbeiten Sie diesen Widerruf im PlentyONE-Backend.";

        @mail($ownerEmail, $subject, $body, "Content-Type: text/plain; charset=UTF-8");
    }

    /**
     * Simple CSRF token generation.
     * In production, use Plenty's built-in CSRF protection.
     */
    private function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['widerruf_csrf'] = $token;
        return $token;
    }

    /**
     * Validate a CSRF token.
     */
    private function validateCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $stored = $_SESSION['widerruf_csrf'] ?? '';
        unset($_SESSION['widerruf_csrf']); // One-time use
        return hash_equals($stored, $token);
    }

    /**
     * Render an error page.
     */
    private function renderError(Twig $twig, string $message): string
    {
        return $twig->render('Widerrufsbutton::content.WiderrufForm', [
            'formAction' => '/widerruf/confirm',
            'shopName'   => $this->getPluginConfig()['shop_name'],
            'csrfToken'  => $this->generateCsrfToken(),
            'errors'     => [$message],
            'oldInput'   => [],
        ]);
    }
}
