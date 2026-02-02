'use client';

import * as React from 'react';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { HugeiconsIcon } from '@hugeicons/react';
import { Calendar03Icon } from '@hugeicons/core-free-icons';

interface DatePickerProps {
  label?: string;
  value?: string;
  onChange?: (date: string) => void;
  placeholder?: string;
}

export function DatePicker({ label, value, onChange, placeholder = 'TT.MM.JJJJ' }: DatePickerProps) {
  const [open, setOpen] = React.useState(false);
  const [inputValue, setInputValue] = React.useState('');

  // Convert string date (YYYY-MM-DD) to Date object for calendar
  const dateValue = value ? new Date(value) : undefined;

  // Sync input value with external value
  React.useEffect(() => {
    if (value) {
      setInputValue(formatDisplayDate(value));
    } else {
      setInputValue('');
    }
  }, [value]);

  const handleSelect = (date: Date | undefined) => {
    if (date && onChange) {
      const formatted = formatToISO(date);
      onChange(formatted);
    }
    setOpen(false);
  };

  const formatToISO = (date: Date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const formatDisplayDate = (dateStr: string) => {
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '';
    return date.toLocaleDateString('de-DE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  };

  const parseInputDate = (input: string): Date | null => {
    // Try parsing DD.MM.YYYY format
    const parts = input.split('.');
    if (parts.length === 3) {
      const day = parseInt(parts[0], 10);
      const month = parseInt(parts[1], 10) - 1;
      const year = parseInt(parts[2], 10);
      const date = new Date(year, month, day);
      if (!isNaN(date.getTime()) && date.getDate() === day && date.getMonth() === month) {
        return date;
      }
    }
    return null;
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    setInputValue(newValue);

    // Try to parse the date as user types
    const parsed = parseInputDate(newValue);
    if (parsed && onChange) {
      onChange(formatToISO(parsed));
    }
  };

  const handleInputBlur = () => {
    // On blur, if we can't parse the input, reset to the current value
    const parsed = parseInputDate(inputValue);
    if (!parsed && value) {
      setInputValue(formatDisplayDate(value));
    } else if (!parsed && !value) {
      setInputValue('');
    }
  };

  return (
    <div className='space-y-1.5'>
      {label && <label className='text-xs font-medium text-muted-foreground uppercase tracking-wide'>{label}</label>}
      <div className='relative flex items-center w-full'>
        <Input
          type='text'
          value={inputValue}
          onChange={handleInputChange}
          onBlur={handleInputBlur}
          placeholder={placeholder}
          className='pl-3 pr-8 text-xs'
        />
        <div className='absolute right-1 flex items-center'>
          <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger className='p-1 text-muted-foreground hover:text-foreground cursor-pointer outline-none'>
              <HugeiconsIcon icon={Calendar03Icon} size={14} />
            </PopoverTrigger>
            <PopoverContent className='w-auto overflow-hidden p-0' align='end' sideOffset={8}>
              <Calendar mode='single' selected={dateValue} defaultMonth={dateValue} captionLayout='label' onSelect={handleSelect} />
            </PopoverContent>
          </Popover>
        </div>
      </div>
    </div>
  );
}
