import { GitBranchPlus, Loader2 } from 'lucide-react';

import ContactAnnotationActions from '@/Components/Contacts/ContactAnnotationActions';
import ContactDetailPanel from '@/Components/Contacts/ContactDetailPanel';
import ContactGraphDetailShell from '@/Components/Contacts/ContactGraphDetailShell';
import ContactGraphHoverPopup from '@/Components/Contacts/ContactGraphHoverPopup';
import ContactGraphToolbar from '@/Components/Contacts/Graph/ContactGraphToolbar';
import Button from '@/Components/ui/Button';
import { PageLoading } from '@/Components/ui/LoadingIndicator';
import { useContactGraphState } from '@/hooks/useContactGraphState';

export interface ContactGraphShellProps {
    accountPublicId: string;
    rootNsid: string;
    accountLabel: string;
    onExit: () => void;
}

export default function ContactGraphShell({
    accountPublicId,
    rootNsid,
    accountLabel,
    onExit,
}: ContactGraphShellProps) {
    const {
        shellRef,
        canvasContainerRef,
        loading,
        error,
        loadSnapshot,
        canvasRef,
        panZoom,
        handlePointerMove,
        handleClick,
        clearHovered,
        hoveredNode,
        hovered,
        selectedNode,
        clearSelected,
        toolbar,
        handleExpand,
        isExpandingSelected,
        handleAnnotationUpdated,
        notes,
    } = useContactGraphState({ accountPublicId, rootNsid, onExit });

    return (
        <div ref={shellRef} className="fixed inset-0 z-[100] flex flex-col bg-slate-100">
            <ContactGraphToolbar {...toolbar} />

            <div className="flex min-h-0 flex-1 overflow-hidden">
                <div ref={canvasContainerRef} className="relative min-w-0 flex-1 overflow-hidden">
                    {loading ? (
                        <PageLoading label="Laying out graph…" className="h-full min-h-0" />
                    ) : error ? (
                        <div className="flex h-full flex-col items-center justify-center gap-3">
                            <p className="text-sm text-rose-700">{error}</p>
                            <Button type="button" variant="secondary" size="sm" onClick={() => void loadSnapshot()}>
                                Retry
                            </Button>
                        </div>
                    ) : (
                        <canvas
                            ref={canvasRef}
                            className="h-full w-full cursor-grab active:cursor-grabbing"
                            onWheel={panZoom.onWheel}
                            onPointerDown={panZoom.onPointerDown}
                            onPointerMove={handlePointerMove}
                            onPointerUp={panZoom.onPointerUp}
                            onPointerLeave={(event) => {
                                panZoom.onPointerUp(event);
                                clearHovered();
                            }}
                            onClick={handleClick}
                        />
                    )}

                    {hoveredNode && hovered && !loading && !error ? (
                        <ContactGraphHoverPopup
                            node={hoveredNode}
                            accountLabel={accountLabel}
                            clientX={hovered.clientX}
                            clientY={hovered.clientY}
                        />
                    ) : null}
                </div>

                {selectedNode && !loading && !error ? (
                    <ContactGraphDetailShell onClose={clearSelected}>
                        <ContactDetailPanel
                            key={selectedNode.nsid}
                            accountPublicId={accountPublicId}
                            accountLabel={accountLabel}
                            subject={selectedNode}
                            onClose={clearSelected}
                            actions={
                                <>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        size="sm"
                                        disabled={isExpandingSelected}
                                        onClick={() => void handleExpand(selectedNode.nsid)}
                                    >
                                        {isExpandingSelected ? (
                                            <>
                                                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                                Expanding…
                                            </>
                                        ) : (
                                            <>
                                                <GitBranchPlus className="mr-1.5 h-4 w-4" />
                                                {selectedNode.child_count > 0
                                                    ? 'Re-expand contacts'
                                                    : 'Expand contacts'}
                                            </>
                                        )}
                                    </Button>

                                    {!selectedNode.is_root ? (
                                        <ContactAnnotationActions
                                            accountPublicId={accountPublicId}
                                            contactNsid={selectedNode.nsid}
                                            starred={selectedNode.starred}
                                            note={notes[selectedNode.nsid] ?? null}
                                            onUpdated={handleAnnotationUpdated}
                                        />
                                    ) : null}
                                </>
                            }
                        />
                    </ContactGraphDetailShell>
                ) : null}
            </div>
        </div>
    );
}
