import * as React from "react"
import { 
  ChevronLeft, 
  ChevronRight, 
  ChevronsLeft, 
  ChevronsRight 
} from "lucide-react"
import { Button } from "@/components/ui/button"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

interface PaginationProps {
  totalItems: number
  pagination: {
    pageIndex: number
    pageSize: number
  }
  setPagination: React.Dispatch<React.SetStateAction<{
    pageIndex: number
    pageSize: number
  }>>
}

export function Pagination({
  totalItems,
  pagination,
  setPagination,
}: PaginationProps) {
  const totalPages = Math.ceil(totalItems / pagination.pageSize)
  const start = totalItems === 0 ? 0 : pagination.pageIndex * pagination.pageSize + 1
  const end = Math.min(start + pagination.pageSize - 1, totalItems)

  return (
    <div className="flex items-center justify-between px-3 py-2 bg-[#3b3e4d] border-t-2 border-[#4a4d5c] flex-shrink-0">
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2">
          <p className="text-[11px] font-medium text-slate-400">Einträge pro Seite</p>
          <Select
            value={`${pagination.pageSize}`}
            onValueChange={(value) => {
              setPagination(prev => ({ ...prev, pageSize: Number(value), pageIndex: 0 }))
            }}
          >
            <SelectTrigger size="sm" className="h-7 w-[60px] bg-[#252730] border-[#4a4d5c] text-slate-100 text-[11px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent side="top" className="bg-[#3b3e4d] border-[#4a4d5c] text-slate-100">
              {[10, 25, 50, 100].map((pageSize) => (
                <SelectItem key={pageSize} value={`${pageSize}`} className="text-[11px]">
                  {pageSize}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="text-[11px] font-medium text-slate-400">
          {start}-{end} von {totalItems} Einträgen
        </div>
      </div>

      <div className="flex items-center gap-2">
        <div className="flex items-center text-[11px] font-medium text-slate-100 min-w-[90px] justify-center">
          Seite {pagination.pageIndex + 1} von {totalPages || 1}
        </div>
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            className="hidden h-7 w-7 p-0 lg:flex bg-[#252730] border-[#4a4d5c] text-slate-100 hover:bg-[#ffcc0d] hover:text-[#252730]"
            onClick={() => setPagination(prev => ({ ...prev, pageIndex: 0 }))}
            disabled={pagination.pageIndex === 0}
          >
            <span className="sr-only">Erste Seite</span>
            <ChevronsLeft className="h-3.5 w-3.5" />
          </Button>
          <Button
            variant="outline"
            className="h-7 w-7 p-0 bg-[#252730] border-[#4a4d5c] text-slate-100 hover:bg-[#ffcc0d] hover:text-[#252730]"
            onClick={() => setPagination(prev => ({ ...prev, pageIndex: prev.pageIndex - 1 }))}
            disabled={pagination.pageIndex === 0}
          >
            <span className="sr-only">Vorherige Seite</span>
            <ChevronLeft className="h-3.5 w-3.5" />
          </Button>
          <Button
            variant="outline"
            className="h-7 w-7 p-0 bg-[#252730] border-[#4a4d5c] text-slate-100 hover:bg-[#ffcc0d] hover:text-[#252730]"
            onClick={() => setPagination(prev => ({ ...prev, pageIndex: prev.pageIndex + 1 }))}
            disabled={pagination.pageIndex >= totalPages - 1}
          >
            <span className="sr-only">Nächste Seite</span>
            <ChevronRight className="h-3.5 w-3.5" />
          </Button>
          <Button
            variant="outline"
            className="hidden h-7 w-7 p-0 lg:flex bg-[#252730] border-[#4a4d5c] text-slate-100 hover:bg-[#ffcc0d] hover:text-[#252730]"
            onClick={() => setPagination(prev => ({ ...prev, pageIndex: totalPages - 1 }))}
            disabled={pagination.pageIndex >= totalPages - 1}
          >
            <span className="sr-only">Letzte Seite</span>
            <ChevronsRight className="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>
    </div>
  )
}
