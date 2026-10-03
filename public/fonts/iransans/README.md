# IRANSans / IRANSansX font assets

Buildino is configured to use IRANSansX for Persian UI typography.

Because IRANSans is a licensed commercial typeface, font binaries are not
stored in this repository. During deployment, copy your licensed WOFF2 files
to this directory with these names:

- `IRANSansX-Regular.woff2`
- `IRANSansX-Medium.woff2`
- `IRANSansX-Bold.woff2`

The stylesheet `public/css/buildino-fonts.css` uses `font-display: swap`
and a Persian-capable system fallback stack, so the UI remains usable if the
licensed assets have not yet been provisioned.
