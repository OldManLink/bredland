## Standard filenames and sizes

| Filename                     |                     Size | Purpose                 |
|------------------------------|-------------------------:|-------------------------|
| `favicon.ico`                | 16, 32, 48 (multi-image) | Browser tabs (legacy)   |
| `favicon-16x16.png`          |                    16×16 | Browser                 |
| `favicon-32x32.png`          |                    32×32 | Browser                 |
| `favicon-48x48.png`          |                    48×48 | Windows/browser         |
| `apple-touch-icon.png`       |                  180×180 | iPhone/iPad Home Screen |
| `android-chrome-192x192.png` |                  192×192 | Android/PWA             |
| `android-chrome-512x512.png` |                  512×512 | Android/PWA install     |
| `icon-512.png`               |                  512×512 | Generic manifest icon   |
| `icon-1024.png`              |                1024×1024 | Master archive          |

## iOS specials:

| Filename       |    Size |
|----------------|--------:|
| `ios-120.png`  | 120×120 |
| `ios-152.png`  | 152×152 |
| `ios-167.png`  | 167×167 |
| `ios-180.png`  | 180×180 |
| `icon-128.png` | 128×128 |
| `icon-256.png` | 256×256 |

---

### Directory structure

```
icons/
    icon-1024.png        ← master raster
    icon-512.png
    icon-256.png
    icon-192.png
    apple-touch-icon.png
    android-chrome-512x512.png
    android-chrome-192x192.png
    favicon.ico
    favicon-32x32.png
    favicon-16x16.png
```

## `manifest.json`:

```
"icons": [
  {
    "src": "/icons/android-chrome-192x192.png",
    "sizes": "192x192",
    "type": "image/png"
  },
  {
    "src": "/icons/android-chrome-512x512.png",
    "sizes": "512x512",
    "type": "image/png"
  }
]
```
