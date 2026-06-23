import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface DebtorRow {
    id: number;
    name: string;
    company: string | null;
    paused: boolean;
    pause_reason: string | null;
    outstanding_cents: number;
    open_invoices_count: number;
    pending_drafts_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inbox', href: '/inbox' }];

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, { style: 'currency', currency: 'USD' });
}

export default function InboxIndex({ debtors }: { debtors: DebtorRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inbox" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Debtors</h1>

                {debtors.length === 0 && <p className="text-muted-foreground text-sm">No debtors yet. Seed the demo data to populate the inbox.</p>}

                <div className="grid gap-3">
                    {debtors.map((debtor) => (
                        <Link key={debtor.id} href={`/inbox/${debtor.id}`} className="block">
                            <Card className="hover:bg-muted/50 flex flex-row items-center justify-between p-4 transition-colors">
                                <div className="flex flex-col gap-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{debtor.name}</span>
                                        {debtor.company && <span className="text-muted-foreground text-sm">· {debtor.company}</span>}
                                        {debtor.paused && <Badge variant="destructive">Paused: {debtor.pause_reason}</Badge>}
                                        {debtor.pending_drafts_count > 0 && (
                                            <Badge variant="secondary">{debtor.pending_drafts_count} to approve</Badge>
                                        )}
                                    </div>
                                    <span className="text-muted-foreground text-sm">
                                        {debtor.open_invoices_count} open invoice{debtor.open_invoices_count === 1 ? '' : 's'}
                                    </span>
                                </div>
                                <div className="text-right">
                                    <div className="font-mono font-semibold">{money(debtor.outstanding_cents)}</div>
                                    <div className="text-muted-foreground text-xs">outstanding</div>
                                </div>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
