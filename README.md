# Widerrufsbutton — plentyShop LTS Plugin

DSGVO-konforme Widerrufsbelehrung als Embed für plentyShop LTS.

## Funktionsweise

1. Händler installiert das Plugin aus dem plentyMarketplace
2. Im Plugin-Konfiguration trägt er seine **Tenant-ID** ein (von https://widerruf.paketwo.de)
3. Das Plugin injiziert automatisch das Embed-Script in den Shop-Footer
4. Die Widerrufsbelehrung + Muster-Widerrufsformular wird mit den Shop-Daten gerendert

## Architektur

```
plenty-widerrufsbutton/
├── plugin.json                          # Plugin-Metadaten (IO ~5.0.0)
├── config.json                          # Plugin-Konfiguration (tenant_id, embed_url, position)
├── marketplace.json                     # plentyMarketplace-Metadaten
├── src/
│   └── Providers/
│       ├── WiderrufsbuttonServiceProvider.php    # IO.Resources.Import → Script-Injection
│       └── WiderrufsbuttonRouteServiceProvider.php  # Routen für interaktives Formular (Phase 2)
├── resources/
│   └── views/
│       └── content/
│           ├── EmbedScript.twig          # Embed-Code (Script-Tag + DIV)
│           ├── EmbedStyle.twig           # Container-CSS
│           ├── WiderrufForm.twig         # Interaktives Formular (Ceres-kompatibel)
│           ├── WiderrufConfirm.twig      # Bestätigungsseite
│           └── WiderrufSuccess.twig      # Erfolgsseite
│   ├── js/
│   │   └── widerruf.js                   # Client-seitige Validierung
│   ├── css/
│   │   └── widerruf.css                  # Formular-Styling
│   └── lang/
│       ├── de.properties                 # Deutsche Übersetzungen
│       └── en.properties                 # Englische Übersetzungen
└── meta/
    └── images/                           # Plugin-Icons
```

## Installation (Händler)

1. Plugin aus dem plentyMarketplace installieren
2. In **Plugins → Widerrufsbutton → Konfiguration**:
   - **Tenant-ID** eintragen (von widerruf.paketwo.de/dashboard)
   - Position wählen: Footer (Standard) oder Sticky Button
3. Plugin-Set bereitstellen → **Fertig**

## SaaS-Integration

Das Plugin lädt das Script von der SaaS-Plattform:
```
https://widerruf.paketwo.de/e/{tenant_id}.js
```

Das Script ist self-contained (CSS inline), rendert die komplette Widerrufsbelehrung
mit den im SaaS-Dashboard konfigurierten Shop-Daten.

## Technische Details

### ServiceProvider (IO.Resources.Import)

```php
$dispatcher->listen('IO.Resources.Import', function (ResourceContainer $container) {
    $container->addScriptTemplate('Widerrufsbutton::content.EmbedScript');
}, 0);
```

- `IO.Resources.Import` ist das LTS-Äquivalent zum alten Ceres-Template-Container
- Das Script wird im Footer (`Footer.twig`) des plentyShop LTS eingebunden
- `defer`-Attribut sorgt für nicht-blockierendes Laden

### Konfiguration

```json
{
    "tenant_id": "uuid-from-dashboard",
    "embed_url": "https://widerruf.paketwo.de",
    "embed_position": "footer"
}
```

### Fallback (noscript)

Wenn JavaScript deaktiviert ist, zeigt das Plugin einen Direktlink zur SaaS-Seite an.

## Roadmap

- [ ] Phase 2: Interaktives Widerrufsformular (Order-Lookup + E-Mail) für LTS portieren
- [ ] Phase 3: Multi-Language Support (EN, FR, ES)
- [ ] Phase 4: Admin-Dashboard-Widget (Widerruf-Statistiken im plentyBackend)

## Verwandte Projekte

| Projekt | Pfad | Status |
|---------|------|--------|
| SaaS-Plattform | `C:\Users\vcrom\projects\widerruf-saas\` | ✅ Live (widerruf.paketwo.de) |
| Shopify App | `C:\Users\vcrom\PycharmProjects\vagabond\Cloud_Projects\widerrufsbutton\shopify\` | ✅ Bestehend |
| Standalone Embed | `C:\Users\vcrom\widerruf-script\` | ✅ Fertig |
