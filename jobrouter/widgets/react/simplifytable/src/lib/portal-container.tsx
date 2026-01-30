import * as React from 'react';

type PortalContainerRef = React.RefObject<HTMLElement | null> | null;

const PortalContainerContext = React.createContext<PortalContainerRef>(null);

function PortalContainerProvider({
  containerRef,
  children,
}: {
  containerRef: React.RefObject<HTMLElement | null>;
  children: React.ReactNode;
}) {
  return <PortalContainerContext.Provider value={containerRef}>{children}</PortalContainerContext.Provider>;
}

function usePortalContainer() {
  return React.useContext(PortalContainerContext);
}

export { PortalContainerProvider, usePortalContainer };
