/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                "surface-dim": "#121414",
                "tertiary-fixed": "#e4e1e5",
                "secondary-fixed": "#dae2fd",
                "surface-container-lowest": "#0c0f0f",
                "secondary-container": "#3f465c",
                "on-error-container": "#ffdad6",
                "on-secondary-fixed-variant": "#3f465c",
                "surface-bright": "#37393a",
                "surface-tint": "#ffb95f",
                "primary-fixed": "#ffddb8",
                "tertiary-container": "#b2b0b4",
                "on-tertiary-fixed-variant": "#47464a",
                "on-primary-fixed-variant": "#653e00",
                "on-primary": "#472a00",
                "surface-container": "#1e2020",
                "on-primary-container": "#613b00",
                "on-surface-variant": "#d8c3ad",
                "tertiary": "#cecbcf",
                "tertiary-fixed-dim": "#c8c6c9",
                "inverse-on-surface": "#2f3131",
                "outline-variant": "#534434",
                "error-container": "#93000a",
                "surface-variant": "#333535",
                "surface-container-highest": "#333535",
                "background": "#121414",
                "on-tertiary-fixed": "#1b1b1e",
                "error": "#ffb4ab",
                "inverse-primary": "#855300",
                "on-background": "#e2e2e2",
                "primary-container": "#f59e0b",
                "primary": "#ffc174",
                "on-error": "#690005",
                "surface": "#121414",
                "outline": "#a08e7a",
                "surface-container-high": "#282a2b",
                "surface-container-low": "#1a1c1c",
                "on-secondary-fixed": "#131b2e",
                "on-primary-fixed": "#2a1700",
                "on-surface": "#e2e2e2",
                "on-secondary": "#283044",
                "inverse-surface": "#e2e2e2",
                "on-tertiary": "#303033",
                "on-secondary-container": "#adb4ce",
                "primary-fixed-dim": "#ffb95f",
                "secondary-fixed-dim": "#bec6e0",
                "secondary": "#bec6e0"
            },
            borderRadius: {
                DEFAULT: "0.25rem",
                lg: "0.5rem",
                xl: "0.75rem",
                full: "9999px"
            },
            spacing: {
                "margin-desktop": "40px",
                "margin-mobile": "16px",
                base: "8px",
                gutter: "24px",
                "container-max": "1200px"
            },
            fontFamily: {
                "label-sm": ["Inter"],
                "headline-md": ["Montserrat"],
                "headline-xl": ["Montserrat"],
                "body-md": ["Inter"],
                "headline-lg": ["Montserrat"],
                "body-lg": ["Inter"],
                "headline-lg-mobile": ["Montserrat"],
                "label-md": ["Inter"]
            },
            fontSize: {
                "label-sm": ["11px", { lineHeight: "16px", fontWeight: "500" }],
                "headline-md": ["20px", { lineHeight: "28px", fontWeight: "600" }],
                "headline-xl": ["36px", { lineHeight: "44px", letterSpacing: "-0.02em", fontWeight: "700" }],
                "body-md": ["14px", { lineHeight: "20px", fontWeight: "400" }],
                "headline-lg": ["28px", { lineHeight: "36px", letterSpacing: "-0.01em", fontWeight: "700" }],
                "body-lg": ["16px", { lineHeight: "24px", fontWeight: "400" }],
                "headline-lg-mobile": ["22px", { lineHeight: "30px", fontWeight: "700" }],
                "label-md": ["13px", { lineHeight: "18px", letterSpacing: "0.05em", fontWeight: "600" }]
            }
        }
    }
};
