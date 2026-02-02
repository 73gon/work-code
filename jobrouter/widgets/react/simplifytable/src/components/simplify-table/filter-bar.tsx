import { useState, useRef } from 'react';
import { useSimplifyTable } from '@/lib/simplify-table-context';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { DatePicker } from '@/components/ui/date-picker';
import { HugeiconsIcon } from '@hugeicons/react';
import { FilterIcon, FilterRemoveIcon, Tick01Icon, Cancel01Icon, FloppyDiskIcon, ArrowUp01Icon, ArrowDown01Icon } from '@hugeicons/core-free-icons';
import type { FilterConfig, DropdownOption, Filters } from '@/lib/types';

export function FilterBar() {
  const { state, filterConfigs, resetFilters, applyPreset, addFilterPreset, fetchData } = useSimplifyTable();

  const [savingPreset, setSavingPreset] = useState(false);
  const [newPresetName, setNewPresetName] = useState('');
  const [isCollapsed, setIsCollapsed] = useState(false);
  const presetNameInputRef = useRef<HTMLInputElement>(null);

  // Get visible presets for the select
  const visiblePresets = state.filterPresets.filter((p) => p.visible !== false);

  const handlePresetChange = (value: string | null) => {
    if (!value || value === 'individual') return;
    applyPreset(value);
  };

  const handleSavePreset = () => {
    if (!newPresetName.trim()) return;

    const newPresetId = `custom-${Date.now()}`;
    const newPreset = {
      id: newPresetId,
      label: newPresetName.trim(),
      filters: { ...state.filters },
      sort: state.sortColumn ? { column: state.sortColumn, direction: state.sortDirection } : undefined,
      visible: true,
      isCustom: true,
    };

    addFilterPreset(newPreset);
    applyPreset(newPresetId); // Auto-select the new preset
    setNewPresetName('');
    setSavingPreset(false);
  };

  const handleCancelSave = () => {
    setNewPresetName('');
    setSavingPreset(false);
  };

  const handleApplyFilters = () => {
    fetchData({ filters: state.filters });
  };

  return (
    <div className='bg-card rounded-xl border p-4 mb-4 shrink-0'>
      {/* Header with collapse toggle */}
      <div className={`flex items-center justify-between ${isCollapsed ? '' : 'mb-4'}`}>
        <span className='text-sm font-medium text-muted-foreground'>Filter</span>
        <Button variant='ghost' size='icon' className='h-6 w-6' onClick={() => setIsCollapsed(!isCollapsed)}>
          <HugeiconsIcon icon={isCollapsed ? ArrowDown01Icon : ArrowUp01Icon} size={14} />
        </Button>
      </div>

      {!isCollapsed && (
        <>
          {/* Filter Grid */}
          <div className='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4'>
            {filterConfigs
              .filter((f) => f.visible)
              .map((config) => (
                <FilterItem key={config.id} config={config} />
              ))}
          </div>

          {/* Actions Row */}
          <div className='flex flex-wrap items-center justify-between gap-4 pt-2 border-t'>
            {/* Preset Select and Save */}
            <div className='flex items-center gap-2'>
              <Select value={state.selectedPreset || 'individual'} onValueChange={handlePresetChange}>
                <SelectTrigger className='w-50'>
                  <SelectValue>
                    {state.selectedPreset === 'individual' || !state.selectedPreset
                      ? 'Individuell'
                      : visiblePresets.find((p) => p.id === state.selectedPreset)?.label || 'Filter auswählen...'}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='individual'>Individuell</SelectItem>
                  {visiblePresets.map((preset) => (
                    <SelectItem key={preset.id} value={preset.id}>
                      {preset.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              {!savingPreset ? (
                <Button variant='outline' onClick={() => setSavingPreset(true)} className='gap-1 h-7.25'>
                  <HugeiconsIcon icon={FloppyDiskIcon} size={16} />
                  Filter speichern
                </Button>
              ) : (
                <div className='relative flex items-center'>
                  <Input
                    ref={presetNameInputRef}
                    value={newPresetName}
                    onChange={(e) => setNewPresetName(e.target.value)}
                    placeholder='Filtername...'
                    className='w-38.75 h-7 pr-14'
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') handleSavePreset();
                      if (e.key === 'Escape') handleCancelSave();
                    }}
                    autoFocus
                  />
                  <div className='absolute right-1 flex items-center gap-0.5'>
                    <button
                      type='button'
                      className='p-1 rounded text-green-500 hover:text-green-600 hover:bg-green-500/10 disabled:opacity-50 disabled:cursor-not-allowed'
                      onClick={handleSavePreset}
                      disabled={!newPresetName.trim()}
                    >
                      <HugeiconsIcon icon={Tick01Icon} size={16} />
                    </button>
                    <button type='button' className='p-1 rounded text-red-500 hover:text-red-600 hover:bg-red-500/10' onClick={handleCancelSave}>
                      <HugeiconsIcon icon={Cancel01Icon} size={16} />
                    </button>
                  </div>
                </div>
              )}
            </div>

            {/* Apply and Reset Buttons */}
            <div className='flex items-center gap-2'>
              <Button onClick={handleApplyFilters} className='gap-1'>
                <HugeiconsIcon icon={FilterIcon} size={16} />
                Filter anwenden
              </Button>
              <Button variant='destructive' onClick={resetFilters} className='gap-1'>
                <HugeiconsIcon icon={FilterRemoveIcon} size={16} />
                Zurücksetzen
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

interface FilterItemProps {
  config: FilterConfig;
}

function FilterItem({ config }: FilterItemProps) {
  const { state, setFilters } = useSimplifyTable();

  if (config.type === 'dropdown') {
    const options = config.options as DropdownOption[];
    const value = state.filters[config.filterKey as keyof Filters] as string;
    const getLabel = (id: string) => {
      if (id === 'all') return 'Alle';
      return options.find((opt) => opt.id === id)?.label || id;
    };

    return (
      <div className='space-y-1.5'>
        <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
        <Select value={value || 'all'} onValueChange={(val) => setFilters({ [config.filterKey]: val })}>
          <SelectTrigger className='w-full'>
            <SelectValue>{getLabel(value || 'all')}</SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>Alle</SelectItem>
            {options.map((opt) => (
              <SelectItem key={opt.id} value={opt.id}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
    );
  }

  if (config.type === 'autocomplete') {
    return <AutocompleteFilter config={config} />;
  }

  if (config.type === 'daterange') {
    const rangeOptions = config.options as { fromId: string; toId: string };
    const fromValue = state.filters[rangeOptions.fromId as keyof Filters] as string;
    const toValue = state.filters[rangeOptions.toId as keyof Filters] as string;

    return (
      <div className='space-y-3'>
        <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
        <div className='grid grid-cols-2 gap-2'>
          <DatePicker value={fromValue} onChange={(date) => setFilters({ [rangeOptions.fromId]: date })} placeholder='Von' />
          <DatePicker value={toValue} onChange={(date) => setFilters({ [rangeOptions.toId]: date })} placeholder='Bis' />
        </div>
      </div>
    );
  }

  if (config.type === 'numberrange') {
    const rangeOptions = config.options as { fromId: string; toId: string };
    const fromValue = state.filters[rangeOptions.fromId as keyof Filters] as string;
    const toValue = state.filters[rangeOptions.toId as keyof Filters] as string;

    return (
      <div className='space-y-1.5'>
        <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
        <div className='flex items-center gap-2'>
          <Input
            type='number'
            step='0.01'
            placeholder='Von'
            value={fromValue || ''}
            onChange={(e) => setFilters({ [rangeOptions.fromId]: e.target.value })}
            className='flex-1 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none'
          />
          <span className='text-muted-foreground'>-</span>
          <Input
            type='number'
            step='0.01'
            placeholder='Bis'
            value={toValue || ''}
            onChange={(e) => setFilters({ [rangeOptions.toId]: e.target.value })}
            className='flex-1 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none'
          />
        </div>
      </div>
    );
  }

  // Text input
  const value = state.filters[config.filterKey as keyof Filters] as string;

  return (
    <div className='space-y-1.5'>
      <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
      <Input value={value || ''} onChange={(e) => setFilters({ [config.filterKey]: e.target.value })} placeholder={`${config.label} eingeben...`} />
    </div>
  );
}

interface AutocompleteFilterProps {
  config: FilterConfig;
}

function AutocompleteFilter({ config }: AutocompleteFilterProps) {
  const { state, setFilters } = useSimplifyTable();
  const [open, setOpen] = useState(false);
  const [inputValue, setInputValue] = useState('');

  const options = config.options as DropdownOption[];
  const selectedValues = state.filters[config.filterKey as keyof Filters] as string[];

  const filteredOptions = options.filter((opt) => {
    const matchesSearch = opt.label.toLowerCase().includes(inputValue.toLowerCase());
    const notSelected = !selectedValues.includes(opt.id);
    return matchesSearch && notSelected;
  });

  const handleSelect = (value: string) => {
    const newValues = [...selectedValues, value];
    setFilters({ [config.filterKey]: newValues });
    setInputValue('');
  };

  const handleRemove = (value: string) => {
    const newValues = selectedValues.filter((v) => v !== value);
    setFilters({ [config.filterKey]: newValues });
  };

  const getLabel = (id: string) => {
    return options.find((opt) => opt.id === id)?.label || id;
  };

  return (
    <div className='space-y-1.5'>
      <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{config.label}</label>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger className='h-7 w-full rounded-md border border-input bg-input/20 px-3 py-1 text-xs ring-offset-background cursor-pointer flex flex-wrap gap-1 items-center text-left overflow-hidden'>
          <div className='flex flex-wrap gap-1 items-center flex-1'>
            {selectedValues.map((val) => (
              <Badge key={val} variant='secondary' className='gap-1 bg-primary text-primary-foreground h-4.5'>
                <span className='max-w-25 truncate text-2xs'>{getLabel(val)}</span>
                <span
                  role='button'
                  tabIndex={0}
                  onClick={(e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    handleRemove(val);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                      e.stopPropagation();
                      e.preventDefault();
                      handleRemove(val);
                    }
                  }}
                  className='hover:bg-primary-foreground/20 rounded-full p-0.5 cursor-pointer'
                >
                  <HugeiconsIcon icon={Cancel01Icon} size={10} />
                </span>
              </Badge>
            ))}
            {selectedValues.length === 0 && <span className='text-muted-foreground'>{config.label} suchen...</span>}
          </div>
        </PopoverTrigger>
        <PopoverContent className='w-(--anchor-width) p-0' align='start'>
          <Command>
            <CommandInput placeholder={`${config.label} suchen...`} value={inputValue} onValueChange={setInputValue} />
            <CommandList>
              <CommandEmpty>Keine Ergebnisse gefunden.</CommandEmpty>
              <CommandGroup>
                {filteredOptions.map((opt) => (
                  <CommandItem key={opt.id} value={opt.label} onSelect={() => handleSelect(opt.id)}>
                    {opt.label}
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  );
}
