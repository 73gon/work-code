import { useState, useCallback, useMemo } from 'react';
import { useSimplifyTable } from '@/lib/simplify-table-context';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { HugeiconsIcon } from '@hugeicons/react';
import {
  Settings02Icon,
  FilterIcon,
  GridTableIcon,
  BookmarkIcon,
  ViewIcon,
  ViewOffIcon,
  DragDropVerticalIcon,
  Delete02Icon,
} from '@hugeicons/core-free-icons';

export function SettingsModal() {
  const [open, setOpen] = useState(false);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger className='inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-accent cursor-pointer'>
        <HugeiconsIcon icon={Settings02Icon} size={18} />
      </DialogTrigger>
      <DialogContent className='max-w-2xl max-h-[85vh]'>
        <DialogHeader>
          <DialogTitle>Einstellungen</DialogTitle>
        </DialogHeader>

        <Tabs defaultValue='filters' className='mt-2'>
          <TabsList className='grid w-full grid-cols-3'>
            <TabsTrigger value='filters' className='gap-2'>
              <HugeiconsIcon icon={FilterIcon} size={16} />
              Filter
            </TabsTrigger>
            <TabsTrigger value='columns' className='gap-2'>
              <HugeiconsIcon icon={GridTableIcon} size={16} />
              Spalten
            </TabsTrigger>
            <TabsTrigger value='presets' className='gap-2'>
              <HugeiconsIcon icon={BookmarkIcon} size={16} />
              Vorlagen
            </TabsTrigger>
          </TabsList>

          <TabsContent value='filters' className='mt-4'>
            <FilterSettingsTab />
          </TabsContent>

          <TabsContent value='columns' className='mt-4'>
            <ColumnSettingsTab />
          </TabsContent>

          <TabsContent value='presets' className='mt-4'>
            <PresetSettingsTab />
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
}

function FilterSettingsTab() {
  const { state, toggleFilterVisibility, filterConfigs } = useSimplifyTable();
  const filters = useMemo(() => filterConfigs, [filterConfigs]);

  return (
    <div className='space-y-4'>
      <p className='text-sm text-muted-foreground'>Wählen Sie aus, welche Filter angezeigt werden sollen.</p>
      <ScrollArea className='h-100 pr-4'>
        <div className='space-y-0.5'>
          {filters.map((filter) => {
            const isVisible = state.visibleFilters.includes(filter.id);
            return (
              <div key={filter.id} className='flex items-center justify-between py-1.5 px-2 rounded hover:bg-muted/50 transition-colors'>
                <span className='text-sm'>{filter.label}</span>
                <Button
                  variant='ghost'
                  size='icon'
                  className={`h-7 w-7 ${isVisible ? 'text-primary' : 'text-muted-foreground'}`}
                  onClick={() => toggleFilterVisibility(filter.id)}
                >
                  <HugeiconsIcon icon={isVisible ? ViewIcon : ViewOffIcon} size={18} />
                </Button>
              </div>
            );
          })}
        </div>
      </ScrollArea>
    </div>
  );
}

function ColumnSettingsTab() {
  const { state, toggleColumnVisibility, columns } = useSimplifyTable();
  const selectableColumns = useMemo(() => columns.filter((col) => col.id !== 'actions'), [columns]);

  return (
    <div className='space-y-4'>
      <p className='text-sm text-muted-foreground'>Blenden Sie Spalten ein oder aus, indem Sie auf das Auge klicken.</p>
      <ScrollArea className='h-100 pr-4'>
        <div className='space-y-0.5'>
          {selectableColumns.map((column) => {
            const isVisible = state.visibleColumns.includes(column.id);
            return (
              <div key={column.id} className='flex items-center justify-between py-1.5 px-2 rounded hover:bg-muted/50 transition-colors'>
                <span className='text-sm'>{column.label}</span>
                <Button
                  variant='ghost'
                  size='icon'
                  className={`h-7 w-7 ${isVisible ? 'text-primary' : 'text-muted-foreground'}`}
                  onClick={() => toggleColumnVisibility(column.id)}
                >
                  <HugeiconsIcon icon={isVisible ? ViewIcon : ViewOffIcon} size={18} />
                </Button>
              </div>
            );
          })}
        </div>
      </ScrollArea>
    </div>
  );
}

function PresetSettingsTab() {
  const { state, togglePresetVisibility, removeFilterPreset, reorderPresets } = useSimplifyTable();
  const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
  const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);

  const handleDragStart = useCallback((e: React.DragEvent, index: number) => {
    setDraggedIndex(index);
    e.dataTransfer.effectAllowed = 'move';
  }, []);

  const handleDragOver = useCallback(
    (e: React.DragEvent, index: number) => {
      e.preventDefault();
      if (draggedIndex === null) return;
      setDragOverIndex(index);
    },
    [draggedIndex],
  );

  const handleDrop = useCallback(
    (e: React.DragEvent, targetIndex: number) => {
      e.preventDefault();
      if (draggedIndex === null || draggedIndex === targetIndex) return;

      reorderPresets(draggedIndex, targetIndex);
      setDraggedIndex(null);
      setDragOverIndex(null);
    },
    [draggedIndex, reorderPresets],
  );

  const handleDragEnd = useCallback(() => {
    setDraggedIndex(null);
    setDragOverIndex(null);
  }, []);

  return (
    <div className='space-y-4'>
      <p className='text-sm text-muted-foreground'>Verwalten Sie Ihre Filtervorlagen. Ziehen Sie zum Neuordnen, oder blenden Sie Vorlagen aus.</p>
      <ScrollArea className='h-100 pr-4'>
        <div className='space-y-0.5'>
          {state.filterPresets.map((preset, index) => (
            <div
              key={preset.id}
              draggable
              onDragStart={(e) => handleDragStart(e, index)}
              onDragOver={(e) => handleDragOver(e, index)}
              onDragLeave={() => setDragOverIndex(null)}
              onDrop={(e) => handleDrop(e, index)}
              onDragEnd={handleDragEnd}
              className={`
                flex items-center justify-between py-1.5 px-2 rounded
                hover:bg-muted/50 transition-colors cursor-grab active:cursor-grabbing
                ${draggedIndex === index ? 'opacity-50' : ''}
                ${dragOverIndex === index ? 'bg-primary/10' : ''}
              `}
            >
              <div className='flex items-center gap-2'>
                <HugeiconsIcon icon={DragDropVerticalIcon} size={14} className='text-muted-foreground' />
                <span className='text-sm'>{preset.label}</span>
              </div>
              <div className='flex items-center gap-0.5'>
                <Button
                  variant='ghost'
                  size='icon'
                  className={`h-7 w-7 ${preset.visible !== false ? 'text-primary' : 'text-muted-foreground'}`}
                  onClick={() => togglePresetVisibility(preset.id)}
                >
                  <HugeiconsIcon icon={preset.visible !== false ? ViewIcon : ViewOffIcon} size={18} />
                </Button>
                <Button
                  variant='ghost'
                  size='icon'
                  className='h-7 w-7 text-destructive hover:text-destructive hover:bg-destructive/10'
                  onClick={() => removeFilterPreset(preset.id)}
                >
                  <HugeiconsIcon icon={Delete02Icon} size={18} />
                </Button>
              </div>
            </div>
          ))}
        </div>
      </ScrollArea>
    </div>
  );
}
