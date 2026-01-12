import * as React from 'react';
import { Tooltip as BaseTooltip } from '@base-ui/react/tooltip';

import { cn } from '@/lib/utils';

interface TooltipProps {
  children: React.ReactNode;
  content: React.ReactNode;
  className?: string;
  side?: 'top' | 'bottom' | 'left' | 'right';
}

function Tooltip({ children, content, className, side = 'top' }: TooltipProps) {
  return (
    <BaseTooltip.Root>
      <BaseTooltip.Trigger render={<span className='inline-flex' />}>{children}</BaseTooltip.Trigger>
      <BaseTooltip.Portal>
        <BaseTooltip.Positioner side={side} sideOffset={4}>
          <BaseTooltip.Popup
            className={cn(
              'bg-[#252730] text-slate-100 z-50 overflow-hidden rounded-md border border-[#4a4d5c] px-3 py-1.5 text-[11px] shadow-md',
              className,
            )}
          >
            <BaseTooltip.Arrow className='fill-[#252730] stroke-[#4a4d5c]' />
            {content}
          </BaseTooltip.Popup>
        </BaseTooltip.Positioner>
      </BaseTooltip.Portal>
    </BaseTooltip.Root>
  );
}

export { Tooltip };
