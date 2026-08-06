import { Link } from '@inertiajs/react';
import { ComponentProps } from 'react';
import { buttonClasses, type ButtonSize, type ButtonVariant } from './Button';

type LinkButtonProps = Omit<ComponentProps<typeof Link>, 'size'> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
};

export function LinkButton({ variant = 'primary', size = 'md', className = '', ...props }: LinkButtonProps) {
  return <Link className={buttonClasses(variant, size, className)} {...props} />;
}
