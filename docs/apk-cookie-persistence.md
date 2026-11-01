# Persistensi Cookie Login di APK (Capacitor WebView)

> Status: **source proyek Android/Capacitor tidak berada di repo ini.**
> APK (`com.mivuww.lifevuww`) dibangun dari proyek Capacitor terpisah yang memuat
> situs remote (`server.url: https://lifevuww.42web.io`, lihat `capacitor.config.json`
> di dalam APK). Dokumen ini adalah panduan penerapan saat proyek APK tersebut diedit.

## Konteks

Sejak commit `27aa00b` (Login Persisten), server menerbitkan:

- Cookie sesi `PHPSESSID` ber-masa-berlaku 30 hari (`SESSION_LIFETIME_DAYS=30`).
- Cookie remember-me `lv_remember` (selector:validator, 30 hari, dirotasi server
  pada tiap pemakaian).

Keduanya adalah **persistent cookie** (punya tanggal kedaluwarsa, bukan session
cookie), sehingga Android WebView umumnya sudah menyimpannya ke disk secara
otomatis. Panggilan `CookieManager.getInstance().flush()` bersifat belt-and-braces:
memaksa cookie langsung tertulis ke penyimpanan flash begitu login selesai dan
saat aplikasi masuk background, sehingga pembunuhan proses oleh OS tidak
menghilangkan login.

## Implementasi di proyek Capacitor (MainActivity)

`android/app/src/main/java/com/mivuww/lifevuww/MainActivity.java`:

```java
package com.mivuww.lifevuww;

import android.os.Bundle;
import android.webkit.CookieManager;
import android.webkit.WebView;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        Bridge bridge = this.bridge;
        // Bungkus client bawaan Capacitor agar perilaku internal tidak hilang
        bridge.getWebView().setWebViewClient(new BridgeWebViewClient(bridge) {
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                // Cookie tersimpan ke flash tepat setelah halaman (login) selesai dimuat
                CookieManager.getInstance().flush();
            }
        });
    }

    @Override
    public void onPause() {
        super.onPause();
        // Cookie aman ketika aplikasi ditutup/di-background oleh pengguna atau OS
        CookieManager.getInstance().flush();
    }
}
```

Catatan versi: `com.getcapacitor.BridgeWebViewClient` tersedia sejak Capacitor 3.
Pada template Kotlin, override yang sama berlaku:

```kotlin
override fun onPause() {
    super.onPause()
    CookieManager.getInstance().flush()
}
```

## Verifikasi setelah build

1. Install APK, login sekali, lalu **Force stop** aplikasi dari Pengaturan Android
   (bukan hanya tombol back) dan buka kembali — harus tetap masuk.
2. Reboot HP, buka aplikasi — harus tetap masuk (cookie flash sudah ter-flush).
3. Di web (`lifevuww.42web.io`), perilaku sama berlaku tanpa perubahan apa pun
   karena seluruh mekanismenya ada di sisi server.
