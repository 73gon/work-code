import { useRef } from 'react';
import { SimplifyTable } from '@/components/simplify-table';
import { PortalContainerProvider } from '@/lib/portal-container';

export function App() {
  const portalContainerRef = useRef<HTMLDivElement | null>(null);

  return (
    <div ref={portalContainerRef} className='simplifytable-widget h-full'>
      <PortalContainerProvider containerRef={portalContainerRef}>
        <SimplifyTable />
      </PortalContainerProvider>
    </div>
  );
}

export default App;
