import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            {/*
             * No `block w-full` and no `mr-2`: `DropdownMenuItem` now carries
             * `flex w-full … gap-2` itself, and `block` would win the merge and
             * undo it. The gap replaces the margin at the same 0.5rem.
             */}
            <DropdownMenuItem asChild>
                <Link href={edit()} prefetch>
                    <Settings />
                    Settings
                </Link>
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            <DropdownMenuItem asChild>
                <Link
                    href={logout()}
                    as="button"
                    onClick={() => router.flushAll()}
                    data-test="logout-button"
                >
                    <LogOut />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
