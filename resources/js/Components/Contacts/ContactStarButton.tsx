import { Star } from 'lucide-react';

import Button from '@/Components/Button';
import { cn } from '@/lib/cn';

interface ContactStarButtonProps {
    starred: boolean;
    disabled?: boolean;
    onToggle: () => void;
    size?: 'sm' | 'md';
}

export default function ContactStarButton({
    starred,
    disabled = false,
    onToggle,
    size = 'sm',
}: ContactStarButtonProps) {
    return (
        <Button
            type="button"
            variant="ghost"
            size={size}
            disabled={disabled}
            aria-label={starred ? 'Remove star' : 'Star contact'}
            aria-pressed={starred}
            title={starred ? 'Starred' : 'Star'}
            onClick={onToggle}
            className={cn(starred ? 'text-amber-500 hover:text-amber-600' : 'text-slate-400 hover:text-amber-500')}
        >
            <Star className={cn('h-4 w-4', starred ? 'fill-current' : '')} />
        </Button>
    );
}
