import * as React from 'react';
import { type FilterState } from './SimplifyTable';
import { type JrDropdownOptions } from '@/lib/jr-utils';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Check, RotateCcw, TriangleAlert, ArrowRightLeft } from 'lucide-react';
import { Combobox, ComboboxContent, ComboboxList, ComboboxItem, ComboboxChips, ComboboxChip, ComboboxChipsInput } from '@/components/ui/combobox';

interface FilterBarProps {
  filters: FilterState;
  setFilters: React.Dispatch<React.SetStateAction<FilterState>>;
  dropdownOptions: JrDropdownOptions;
  onApply: () => void;
  onReset: () => void;
}

export function FilterBar({ filters, setFilters, dropdownOptions, onApply, onReset }: FilterBarProps) {
  const updateFilter = (key: keyof FilterState, value: any) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
  };

  // Get display label for dropdown value
  const getDisplayLabel = (options: { id: string; label: string }[], value: string) => {
    if (value === 'all') return 'Alle';
    const opt = options.find((o) => o.id === value);
    return opt?.label || value;
  };

  const MultiSelect = ({ label, filterKey, options }: { label: string; filterKey: keyof FilterState; options: { id: string; label: string }[] }) => {
    const selectedValues = (filters[filterKey] as string[]) || [];

    return (
      <div className='flex flex-col gap-1.5'>
        <Label className='text-[10px] font-bold uppercase tracking-wider text-slate-400'>{label}</Label>
        <Combobox value={selectedValues} onValueChange={(values) => updateFilter(filterKey, values)} multiple>
          <ComboboxChips className='bg-[#252730] border-[#4a4d5c] focus-within:border-[#ffcc0d] min-h-[40px] text-[11px]'>
            {selectedValues.map((val) => {
              const opt = options.find((o) => o.id === val);
              return (
                <ComboboxChip key={val} id={val} className='bg-[#ffcc0d] text-[#252730] text-[11px]'>
                  {opt?.label || val}
                </ComboboxChip>
              );
            })}
            <ComboboxChipsInput placeholder='Suchen...' className='text-slate-100 placeholder:text-slate-500 text-[11px]' />
          </ComboboxChips>
          <ComboboxContent className='bg-[#3b3e4d] border-[#4a4d5c] text-slate-100'>
            <ComboboxList>
              {options.map((opt) => (
                <ComboboxItem key={opt.id} value={opt.id} className='hover:bg-[#4a4d5c] focus:bg-[#4a4d5c] text-[11px]'>
                  {opt.label}
                </ComboboxItem>
              ))}
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
      </div>
    );
  };

  const DropdownFilter = ({
    label,
    filterKey,
    options,
  }: {
    label: string;
    filterKey: keyof FilterState;
    options: { id: string; label: string }[];
  }) => (
    <div className='flex flex-col gap-1.5'>
      <Label className='text-[10px] font-bold uppercase tracking-wider text-slate-400'>{label}</Label>
      <Select value={filters[filterKey] as string} onValueChange={(val) => updateFilter(filterKey, val)}>
        <SelectTrigger className='bg-[#252730] border-[#4a4d5c] text-slate-100 h-[40px] text-[11px]'>
          <SelectValue>{getDisplayLabel(options, filters[filterKey] as string)}</SelectValue>
        </SelectTrigger>
        <SelectContent className='bg-[#3b3e4d] border-[#4a4d5c] text-slate-100'>
          <SelectGroup>
            <SelectItem value='all' className='text-[11px]'>
              Alle
            </SelectItem>
            {options.map((opt) => (
              <SelectItem key={opt.id} value={opt.id} className='text-[11px]'>
                {opt.label}
              </SelectItem>
            ))}
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>
  );

  const TextFilter = ({ label, filterKey, placeholder }: { label: string; filterKey: keyof FilterState; placeholder?: string }) => (
    <div className='flex flex-col gap-1.5'>
      <Label className='text-[10px] font-bold uppercase tracking-wider text-slate-400'>{label}</Label>
      <Input
        value={filters[filterKey] as string}
        onChange={(e) => updateFilter(filterKey, e.target.value)}
        className='bg-[#252730] border-[#4a4d5c] text-slate-100 h-[40px] text-[11px]'
        placeholder={placeholder}
      />
    </div>
  );

  return (
    <Card className='p-3 bg-[#3b3e4d] border-[#4a4d5c] shadow-2xl rounded-2xl flex flex-col gap-3'>
      <div className='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3'>
        {/* Status */}
        <DropdownFilter label='Status' filterKey='status' options={dropdownOptions.status} />

        {/* Schritt */}
        <MultiSelect label='Schritt' filterKey='schritt' options={dropdownOptions.schritt} />

        {/* DokumentId */}
        <TextFilter label='DokumentId' filterKey='dokumentId' placeholder='ID eingeben...' />

        {/* Bearbeiter */}
        <TextFilter label='Bearbeiter' filterKey='bearbeiter' />

        {/* Rolle */}
        <TextFilter label='Rolle' filterKey='rolle' />

        {/* Gesellschaft */}
        <MultiSelect label='Gesellschaft' filterKey='gesellschaft' options={dropdownOptions.gesellschaft} />

        {/* Fonds */}
        <MultiSelect label='Fonds' filterKey='fonds' options={dropdownOptions.fonds} />

        {/* Kreditor */}
        <TextFilter label='Kreditor' filterKey='kreditor' />

        {/* Rechnungsnummer */}
        <TextFilter label='Rechnungsnummer' filterKey='rechnungsnummer' />

        {/* Rechnungstyp */}
        <TextFilter label='Rechnungstyp' filterKey='rechnungstyp' />

        {/* Rechnungsdatum Range */}
        <div className='flex flex-col gap-1.5'>
          <Label className='text-[10px] font-bold uppercase tracking-wider text-slate-400'>Rechnungsdatum</Label>
          <div className='flex items-center gap-2'>
            <Input
              type='date'
              value={filters.rechnungsdatumFrom}
              onChange={(e) => updateFilter('rechnungsdatumFrom', e.target.value)}
              className='bg-[#252730] border-[#4a4d5c] text-slate-100 h-[40px] flex-1 text-[11px] [color-scheme:dark]'
            />
            <span className='text-slate-500 text-[11px]'>-</span>
            <Input
              type='date'
              value={filters.rechnungsdatumTo}
              onChange={(e) => updateFilter('rechnungsdatumTo', e.target.value)}
              className='bg-[#252730] border-[#4a4d5c] text-slate-100 h-[40px] flex-1 text-[11px] [color-scheme:dark]'
            />
          </div>
        </div>

        {/* Bruttobetrag Range */}
        <div className='flex flex-col gap-1.5'>
          <Label className='text-[10px] font-bold uppercase tracking-wider text-slate-400'>Bruttobetrag</Label>
          <div className='flex items-center gap-2'>
            <Input
              type='number'
              value={filters.bruttobetragFrom}
              onChange={(e) => updateFilter('bruttobetragFrom', e.target.value)}
              className='bg-[#252730] border-[#4a4d5c] text-slate-100 h-[40px] flex-1 text-[11px]'
              placeholder='Von'
            />
            <span className='text-slate-500 text-[11px]'>-</span>
            <Input
              type='number'
              value={filters.bruttobetragTo}
              onChange={(e) => updateFilter('bruttobetragTo', e.target.value)}
              className='bg-[#252730] border-[#4a4d5c] text-slate-100 h-[40px] flex-1 text-[11px]'
              placeholder='Bis'
            />
          </div>
        </div>

        {/* Weiterbelasten */}
        <DropdownFilter label='Weiterbelasten' filterKey='weiterbelasten' options={dropdownOptions.weiterbelasten} />

        {/* Laufzeit */}
        <DropdownFilter label='Laufzeit' filterKey='laufzeit' options={dropdownOptions.laufzeit} />

        {/* Coor */}
        <DropdownFilter label='Coor' filterKey='coor' options={dropdownOptions.coor} />
      </div>

      <div className='flex flex-wrap justify-between items-center gap-3 pt-2 border-t border-[#4a4d5c]'>
        <div className='flex gap-2'>
          <Button
            variant='outline'
            className='bg-[#4a4d5c] border-[#ffcc0d] text-[#ffcc0d] hover:bg-[#ffcc0d] hover:text-[#252730] border font-bold h-[36px] text-[11px]'
            onClick={() => {
              updateFilter('status', 'faellig');
              onApply();
            }}
          >
            <TriangleAlert className='mr-2 h-4 w-4' />
            Überfällige Rechnungen
          </Button>
          <Button
            variant='outline'
            className='bg-[#4a4d5c] border-[#ffcc0d] text-[#ffcc0d] hover:bg-[#ffcc0d] hover:text-[#252730] border font-bold h-[36px] text-[11px]'
            onClick={() => {
              updateFilter('status', 'aktiv_alle');
              updateFilter('schritt', ['4001']);
              onApply();
            }}
          >
            <ArrowRightLeft className='mr-2 h-4 w-4' />
            Coor Schnittstelle
          </Button>
        </div>

        <div className='flex gap-2'>
          <Button className='bg-[#ffcc0d] text-[#252730] hover:bg-[#e6b800] font-bold h-[40px] px-5 text-[11px]' onClick={onApply}>
            <Check className='mr-2 h-4 w-4' />
            Filter anwenden
          </Button>
          <Button variant='destructive' className='bg-red-500 hover:bg-red-600 font-bold h-[40px] px-5 text-[11px]' onClick={onReset}>
            <RotateCcw className='mr-2 h-4 w-4' />
            Zurücksetzen
          </Button>
        </div>
      </div>
    </Card>
  );
}
