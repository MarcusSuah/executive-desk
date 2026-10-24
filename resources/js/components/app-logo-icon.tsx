import type { SVGAttributes } from 'react';

/**
 * Executive Desk mark: a briefcase with a calendar clasp.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M14 6a4 4 0 0 0-4 4v2H6a4 4 0 0 0-4 4v16a4 4 0 0 0 4 4h28a4 4 0 0 0 4-4V16a4 4 0 0 0-4-4h-4v-2a4 4 0 0 0-4-4H14Zm12 6v-2H14v2h12ZM6 16h28v5H23v-1a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v1H6v-5Zm11 9H6v7h28v-7H23v1a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-1Z"
            />
        </svg>
    );
}
