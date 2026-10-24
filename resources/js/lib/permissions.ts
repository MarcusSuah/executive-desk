export type PermissionInfo = {
    value: string;
    label: string;
    description: string;
    group: string;
};

/**
 * Group permissions by area, keeping their order.
 */
export function groupPermissions(
    permissions: PermissionInfo[],
): [string, PermissionInfo[]][] {
    const groups = new Map<string, PermissionInfo[]>();

    for (const permission of permissions) {
        groups.set(permission.group, [
            ...(groups.get(permission.group) ?? []),
            permission,
        ]);
    }

    return [...groups.entries()];
}
