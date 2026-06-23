import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Debtor {
    id: number;
    name: string;
    company: string | null;
    email: string | null;
    phone: string | null;
    tone_policy: string | null;
    paused: boolean;
    pause_reason: string | null;
    paused_at: string | null;
}

interface InvoiceRow {
    id: number;
    number: string;
    currency: string;
    amount_cents: number;
    amount_paid_cents: number;
    outstanding_cents: number;
    status: string;
    due_date: string;
    payment_url: string | null;
    next_step: string;
}

interface ThreadItem {
    id: number;
    direction: string;
    channel: string;
    status: string;
    body: string | null;
    classification: string | null;
    sequence_step: number | null;
    can_approve: boolean;
    created_at: string | null;
}

interface EventItem {
    id: number;
    type: string;
    data: Record<string, unknown> | null;
    created_at: string | null;
}

interface Props {
    debtor: Debtor;
    invoices: InvoiceRow[];
    thread: ThreadItem[];
    events: EventItem[];
}

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, { style: 'currency', currency: 'USD' });
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'paid' || status === 'sent' || status === 'approved') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'partial' || status === 'pending_approval' || status === 'received') return 'secondary';
    return 'outline';
}

const reload = { preserveScroll: true } as const;

export default function InboxShow({ debtor, invoices, thread, events }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Inbox', href: '/inbox' },
        { title: debtor.name, href: `/inbox/${debtor.id}` },
    ];

    const [editingId, setEditingId] = useState<number | null>(null);
    const [editBody, setEditBody] = useState('');

    function startEdit(item: ThreadItem) {
        setEditingId(item.id);
        setEditBody(item.body ?? '');
    }

    function saveEdit(id: number) {
        router.patch(`/messages/${id}`, { body: editBody }, { ...reload, onSuccess: () => setEditingId(null) });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Inbox · ${debtor.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <h1 className="text-xl font-semibold">{debtor.name}</h1>
                        {debtor.company && <span className="text-muted-foreground">· {debtor.company}</span>}
                        {debtor.paused && <Badge variant="destructive">Sequence paused: {debtor.pause_reason}</Badge>}
                    </div>
                    <div className="text-muted-foreground text-sm">
                        {debtor.email ?? 'no email'} · {debtor.phone ?? 'no phone'}
                    </div>
                    {debtor.tone_policy && <div className="text-muted-foreground text-sm italic">Tone policy: {debtor.tone_policy}</div>}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left: invoices + thread */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <section className="flex flex-col gap-3">
                            <h2 className="text-sm font-semibold tracking-wide uppercase">Invoices</h2>
                            {invoices.map((invoice) => (
                                <Card key={invoice.id} className="flex flex-col gap-2 p-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-medium">{invoice.number}</span>
                                            <Badge variant={statusVariant(invoice.status)}>{invoice.status}</Badge>
                                        </div>
                                        <span className="font-mono">{money(invoice.outstanding_cents)} due</span>
                                    </div>
                                    <div className="text-muted-foreground text-sm">
                                        {money(invoice.amount_cents)} total · due {invoice.due_date} · next: {invoice.next_step}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 pt-1">
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => router.post(`/invoices/${invoice.id}/draft`, {}, reload)}
                                        >
                                            Draft reminder
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.post(`/invoices/${invoice.id}/payment-link`, {}, reload)}
                                        >
                                            {invoice.payment_url ? 'Recreate payment link' : 'Create payment link'}
                                        </Button>
                                        {invoice.payment_url && (
                                            <a href={invoice.payment_url} target="_blank" rel="noreferrer" className="text-sm underline">
                                                Open payment link
                                            </a>
                                        )}
                                    </div>
                                </Card>
                            ))}
                        </section>

                        <section className="flex flex-col gap-3">
                            <h2 className="text-sm font-semibold tracking-wide uppercase">Thread</h2>
                            {thread.length === 0 && <p className="text-muted-foreground text-sm">No messages yet.</p>}
                            {thread.map((item) => (
                                <Card
                                    key={item.id}
                                    className={`flex flex-col gap-2 p-4 ${item.direction === 'inbound' ? 'border-l-primary border-l-4' : ''}`}
                                >
                                    <div className="flex items-center gap-2 text-sm">
                                        <Badge variant="outline">{item.direction}</Badge>
                                        <span className="text-muted-foreground">{item.channel}</span>
                                        <Badge variant={statusVariant(item.status)}>{item.status}</Badge>
                                        {item.classification && <Badge variant="secondary">reply: {item.classification}</Badge>}
                                        <span className="text-muted-foreground ml-auto text-xs">{item.created_at}</span>
                                    </div>

                                    {editingId === item.id ? (
                                        <div className="flex flex-col gap-2">
                                            <textarea
                                                className="border-input min-h-24 rounded-md border bg-transparent p-2 text-sm"
                                                value={editBody}
                                                onChange={(e) => setEditBody(e.target.value)}
                                            />
                                            <div className="flex gap-2">
                                                <Button size="sm" onClick={() => saveEdit(item.id)}>
                                                    Save
                                                </Button>
                                                <Button size="sm" variant="ghost" onClick={() => setEditingId(null)}>
                                                    Cancel
                                                </Button>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm whitespace-pre-wrap">{item.body}</p>
                                    )}

                                    {item.can_approve && editingId !== item.id && (
                                        <div className="flex gap-2">
                                            <Button size="sm" onClick={() => router.post(`/messages/${item.id}/approve`, {}, reload)}>
                                                Approve &amp; queue
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => startEdit(item)}>
                                                Edit
                                            </Button>
                                        </div>
                                    )}
                                </Card>
                            ))}
                        </section>
                    </div>

                    {/* Right: append-only event log */}
                    <section className="flex flex-col gap-3">
                        <h2 className="text-sm font-semibold tracking-wide uppercase">Event log</h2>
                        <Card className="p-4">
                            <ol className="flex flex-col gap-3">
                                {events.map((event) => (
                                    <li key={event.id} className="text-sm">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-mono">{event.type}</span>
                                            <span className="text-muted-foreground text-xs">{event.created_at}</span>
                                        </div>
                                        {event.data && Object.keys(event.data).length > 0 && (
                                            <pre className="text-muted-foreground mt-1 overflow-x-auto text-xs">{JSON.stringify(event.data)}</pre>
                                        )}
                                        <Separator className="mt-3" />
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
