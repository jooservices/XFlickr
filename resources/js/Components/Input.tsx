import { forwardRef, type InputHTMLAttributes } from 'react';

import { cn } from '@/lib/cn';
import { inputVariants, type InputSize } from '@/lib/inputVariants';

export interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
    size?: InputSize;
}

const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { size = 'md', className, type = 'text', ...props },
    ref,
) {
    return <input ref={ref} type={type} className={cn(inputVariants({ size }), className)} {...props} />;
});

export default Input;
