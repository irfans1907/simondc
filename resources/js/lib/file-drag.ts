let draggedIds: number[] | null = null;

export function setDraggedIds(ids: number[] | null): void {
    draggedIds = ids;
}

export function getDraggedIds(): number[] | null {
    return draggedIds;
}
