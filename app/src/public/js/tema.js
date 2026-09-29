// Tema de marca (negro + rojo, letras blancas) para Tailwind.
// Se carga justo después del script de Tailwind CDN en cada página.
// Re-mapea las paletas que ya usa la app, así todas las pantallas cambian a la vez:
//   slate  -> negros/grises oscuros (fondos, bordes)
//   blue   -> rojo de marca (acentos, botones principales, enlaces)
//   emerald / amber / red / purple -> versiones oscuras para badges de estado
tailwind.config = {
    theme: {
        extend: {
            colors: {
                slate: {
                    50: '#1f1f1f', 100: '#262626', 200: '#2e2e2e', 300: '#3a3a3a',
                    400: '#8b8b8b', 500: '#a3a3a3', 600: '#c4c4c4', 700: '#e0e0e0',
                    800: '#1c1c1c', 900: '#000000',
                },
                blue: {
                    50: '#2b1010', 100: '#3b1515', 200: '#5a1e1e',
                    500: '#ef4444', 600: '#dc2626', 700: '#b91c1c',
                },
                red: { 50: '#2b1010', 100: '#3b1515', 200: '#5a1e1e' },
                rose: { 500: '#f43f5e' },
                emerald: { 50: '#0d2a1c', 100: '#123524', 500: '#10b981', 600: '#059669', 700: '#047857' },
                amber: { 50: '#2a1f0a', 100: '#3a2a0d', 200: '#5a4213', 300: '#7a5a1a', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
                purple: { 50: '#231433', 100: '#2d1a42' },
            },
        },
    },
};
