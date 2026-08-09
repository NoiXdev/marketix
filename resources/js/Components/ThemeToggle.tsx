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
      className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-surface text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
    >
      <Icon className="h-[18px] w-[18px]" />
    </button>
  );
}
