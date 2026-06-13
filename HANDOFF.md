# Handoff: PlentyShop LTS Plugin

**Datum:** 13.06.2026
**Task:** Widerrufsbutton als plentyShop LTS Plugin (Embed-Variante)
**Deliverable:** `C:\Users\vcrom\Cloud_Projects\plenty-widerrufsbutton\`

## Was wurde gemacht

Das bestehende Ceres-Plugin wurde um PlentyShop-LTS-Kompatibilität erweitert:

### Geänderte Dateien
| Datei | Änderung |
|-------|----------|
| `plugin.json` | v2.0.0, nur noch `IO ~5.0.0` (Ceres entfernt), neue Keywords |
| `src/Providers/WiderrufsbuttonServiceProvider.php` | LTS-Pattern: `IO.Resources.Import`-Event statt alter Template-Container |
| `config.json` | Neue Felder: `tenant_id`, `embed_url`, `embed_position` |
| `marketplace.json` | Neue Kategorien, Embed-Beschreibung |
| `resources/lang/de.properties` | Config-Übersetzungen |
| `resources/lang/en.properties` | Config-Übersetzungen |
| `README.md` | Komplett neu: LTS-Architektur, Installation, SaaS-Integration |

### Neue Dateien
| Datei | Zweck |
|-------|-------|
| `resources/views/content/EmbedScript.twig` | Embed-Code (Script-Tag + DIV-Container) |
| `resources/views/content/EmbedStyle.twig` | Container-CSS |

## Architektur-Entscheidung

**Warum Embed statt Formular-Logik im Plugin?**

Die bestehende Ceres-Version hat das volle Formular-Handling (Order-Lookup per REST-API, E-Mail-Versand, etc.) im Plugin. Für die LTS-Version wurde bewusst der **Embed-Ansatz** gewählt:

1. **Weniger Wartung:** Der Rechtstext und das Muster-Formular werden von der SaaS-Plattform (widerruf.paketwo.de) ausgeliefert — immer aktuell, kein Plugin-Update bei Gesetzesänderungen.
2. **Einfachere Installation:** Händler muss nur die Tenant-ID eintragen. Keine API-Credentials nötig.
3. **Schnellere Markteinführung:** Das Plugin ist sofort funktionsfähig, das interaktive Formular kann später ergänzt werden.

## So funktioniert's

```
Händler installiert Plugin
  → trägt Tenant-ID ein (von widerruf.paketwo.de/dashboard)
  → Plugin injiziert <script src="https://widerruf.paketwo.de/e/{id}.js">
  → Script rendert Widerrufsbelehrung + Muster-Formular
  → View-Zähler läuft im Hintergrund (Analytics)
```

## PlentyShop LTS Pattern

Das Plugin nutzt das offizielle LTS-Pattern aus dem Cookbook:

```php
$dispatcher->listen('IO.Resources.Import', function (ResourceContainer $container) {
    $container->addScriptTemplate('Widerrufsbutton::content.EmbedScript');
}, 0);
```

- `IO.Resources.Import` feuert beim Laden der Shop-Seite
- `addScriptTemplate()` fügt das Twig-Template in `Footer.twig` ein
- Funktioniert ohne Template-Container (alter Ceres-Weg)

## Nächste Schritte

1. **PlentyONE Developer App anlegen** — Client-ID + Secret für den Marketplace
2. **Plugin im plentyMarketplace einreichen** — Kategorien: Rechtssicherheit, DSGVO
3. **Phase 2: Interaktives Formular** — Order-Lookup + E-Mail für LTS portieren (nutzt `WiderrufsbuttonRouteServiceProvider`)

## Verwandte Projekte

| Projekt | Pfad |
|---------|------|
| SaaS-Plattform (Live) | `C:\Users\vcrom\projects\widerruf-saas\` |
| Shopify App | `C:\Users\vcrom\PycharmProjects\vagabond\Cloud_Projects\widerrufsbutton\shopify\` |
| Standalone Embed | `C:\Users\vcrom\widerruf-script\` |
