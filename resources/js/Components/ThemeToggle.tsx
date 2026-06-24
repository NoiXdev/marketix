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
      className="cursor-pointer rounded-lg border p-1 dark:bg-indigo-900 dark:hover:bg-indigo-700"
    >
      <Icon className="h-5 w-5 shrink-0 text-slate-400" />
    </button>
  );
}
