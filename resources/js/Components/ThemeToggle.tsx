import { useTheme, type Theme } from '@/lib/theme';
import { Monitor, Moon, Sun } from 'lucide-react';

const ORDER: Theme[] = ['light', 'dark', 'auto'];

const CONFIG: Record<Theme, { icon: typeof Sun; label: string }> = {
    light: { icon: Sun, label: 'Light' },
    dark: { icon: Moon, label: 'Dark' },
    auto: { icon: Monitor, label: 'Auto' },
};

export default function ThemeToggle() {
    const [theme, setTheme] = useTheme();
    const { icon: Icon, label } = CONFIG[theme];

    const cycle = () => {
        const next = ORDER[(ORDER.indexOf(theme) + 1) % ORDER.length];
        setTheme(next);
    };

    return (
        <button
            onClick={cycle}
            title={`Theme: ${label} (click to change)`}
            aria-label={`Theme: ${label}. Click to change.`}
            className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
        >
            <Icon className="h-4 w-4 shrink-0 text-slate-400" />
            <span className="flex-1 text-left font-medium">{label}</span>
        </button>
    );
}
