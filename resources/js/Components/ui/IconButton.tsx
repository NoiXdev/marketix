import { Link } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

const base =
  'inline-flex items-center justify-center rounded-md p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:opacity-50';
const variants = {
  default: 'text-subtle hover:bg-elevated hover:text-foreground',
  danger: 'text-subtle hover:bg-danger-soft hover:text-danger-foreground',
};

export function IconButton({
  icon: Icon,
  label,
  href,
  onClick,
  variant = 'default',
  disabled,
  spinning,
}: {
  icon: LucideIcon;
  label: string;
  href?: string;
  onClick?: () => void;
  variant?: 'default' | 'danger';
  disabled?: boolean;
  spinning?: boolean;
}) {
  const cls = `${base} ${variants[variant]}`;
  const inner = <Icon className={`h-4 w-4 ${spinning ? 'animate-spin' : ''}`} />;
  if (href) {
    return (
      <Link href={href} title={label} aria-label={label} className={cls}>
        {inner}
      </Link>
    );
  }
  return (
    <button type="button" title={label} aria-label={label} onClick={onClick} disabled={disabled} className={cls}>
      {inner}
    </button>
  );
}
