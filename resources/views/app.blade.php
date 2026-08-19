<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark']) data-theme="{{ $theme ?? 'forest' }}">
    <head>
        @php
            $seo = data_get($page, 'props.seo', []);
            $seoTitle = data_get($seo, 'title') ?: config('app.name', 'Chags');
            $seoDescription = data_get($seo, 'description');
            $canonicalUrl = data_get($seo, 'canonical_url') ?: config('app.url');
            $ogTitle = data_get($seo, 'og_title') ?: $seoTitle;
            $ogDescription = data_get($seo, 'og_description') ?: $seoDescription;
            $ogUrl = data_get($seo, 'og_url') ?: $canonicalUrl;
            $companyLogoUrl = data_get($page, 'props.companyBrand.logoUrl');
            $ogImageUrl = data_get($seo, 'og_image_url') ?: ($companyLogoUrl ? url($companyLogoUrl) : null);
            $ga4MeasurementId = data_get($seo, 'ga4_measurement_id');
            $metaPixelId = data_get($seo, 'meta_pixel_id');
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if ($seoDescription)
            <meta name="description" content="{{ $seoDescription }}">
        @endif
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta name="robots" content="{{ data_get($seo, 'robots', 'index, follow') }}">

        <meta property="og:title" content="{{ $ogTitle }}">
        @if ($ogDescription)
            <meta property="og:description" content="{{ $ogDescription }}">
        @endif
        @if ($ogImageUrl)
            <meta property="og:image" content="{{ $ogImageUrl }}">
        @endif
        <meta property="og:url" content="{{ $ogUrl }}">
        <meta property="og:type" content="{{ data_get($seo, 'og_type', 'website') }}">
        <meta property="og:locale" content="{{ data_get($seo, 'og_locale', 'pt_BR') }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @if ($companyLogoUrl)
            <link rel="icon" href="{{ $companyLogoUrl }}" type="image/webp">
            <link rel="apple-touch-icon" href="{{ $companyLogoUrl }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @if ($ga4MeasurementId)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga4MeasurementId) }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', @json($ga4MeasurementId));
            </script>
        @endif

        @if ($metaPixelId)
            <script>
                !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
                n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
                (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', @json($metaPixelId));
                fbq('track', 'PageView');
            </script>
            <noscript>
                <img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ urlencode($metaPixelId) }}&ev=PageView&noscript=1">
            </noscript>
        @endif

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $seoTitle }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
