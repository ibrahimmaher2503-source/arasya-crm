# Arabic shell keyboard review

Reusable base view: `layouts.shell`. Extend it with a Blade child view and provide a plain-text `title`, optional `active` key, and Laravel's default `ViewErrorBag` as `$errors`. Define the page using a child Blade `@section('content')`; the layout renders it through `@yield('content')`. When no child section is defined, an optional plain-text `$content` fallback is escaped. Blade child templates should continue to escape request-derived values with `{{ }}` and must not emit untrusted HTML. The shell embeds the fixed local `design-system.css`, `ui-forms.css`, `ui-data.css`, `ui-feedback.css` and `design-system.js` files without Vite or a generated manifest. The former unused `rtl-shell.css` palette has been retired. Read the [current component catalog](../../../docs/design-system/README.md).

## Browser review record

Historical review of the earlier shell, preserved below. Current design-system verification is recorded separately in [the acceptance evidence](../../../docs/governance/evidence/design-system-acceptance.md).

Chrome headless / DevTools Protocol review used an emulated 1440 × 1000 desktop and 390 × 844 mobile viewport with deliberately long Arabic title/error strings and long email/reference values. `innerWidth` matched both requested widths; document/body scroll widths did not exceed `clientWidth` (desktop: 1425px client/scroll width with a 15px vertical scrollbar; mobile: 390px). The first Tab exposed the skip link, Enter moved focus to `#main-content`, and dark-theme contrast measured 14.60:1 for body text, 11.44:1 for alert text, and 9.38:1 for the focused outline. The luminance helper was checked against black/white (21:1) and identical colors (1:1) reference ratios. This is emulated-browser evidence, not testing on physical devices.

## Follow-up checklist

- [ ] Repeat the keyboard, long-content, reduced-motion, and dark-theme review on target physical devices before release.
- [ ] Verify native Arabic font rendering and assistive-technology announcements on supported devices.
