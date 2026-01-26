import { useSimplifyTable } from '@/lib/simplify-table-context';
import { Button } from '@/components/ui/button';
import { HugeiconsIcon } from '@hugeicons/react';
import { Add01Icon, MinusSignIcon, RefreshIcon } from '@hugeicons/core-free-icons';
import { SettingsModal } from './settings-modal';

export function TableHeader() {
  const { state, setZoom } = useSimplifyTable();

  const handleZoomIn = () => setZoom(state.zoomLevel + 0.1);
  const handleZoomOut = () => setZoom(state.zoomLevel - 0.1);
  const handleZoomReset = () => setZoom(1.0);

  return (
    <div className='flex items-center justify-between mb-2'>
      <div className='flex items-center gap-2'>
        <h2 className='text-base font-semibold'>Umlauftabelle</h2>
      </div>

      <div className='flex items-center gap-1.5'>
        {/* Zoom Controls */}
        <div className='flex items-center gap-0.5 bg-muted rounded-md p-0.5'>
          <Button variant='ghost' size='icon' className='h-6 w-6' onClick={handleZoomOut} disabled={state.zoomLevel <= 0.5}>
            <HugeiconsIcon icon={MinusSignIcon} size={12} />
          </Button>
          <Button variant='ghost' size='icon' className='h-6 w-6' onClick={handleZoomReset}>
            <HugeiconsIcon icon={RefreshIcon} size={12} />
          </Button>
          <Button variant='ghost' size='icon' className='h-6 w-6' onClick={handleZoomIn} disabled={state.zoomLevel >= 2.0}>
            <HugeiconsIcon icon={Add01Icon} size={12} />
          </Button>
        </div>

        {/* Settings */}
        <SettingsModal />
      </div>
    </div>
  );
}
