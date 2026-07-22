<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Plus, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface Result {
    turnover: number; rate: number | null; rate_name: string | null;
    rate_error: string | null; tax: number | null;
    from: string; to: string; posted: boolean;
    posted_tax: number | null; amended: boolean;
}
interface Rate { id: number; name: string; code: string; rate: number; period: string; active: boolean }

const props = defineProps<{
    result: Result;
    taxRegime: string;
    rates: Rate[];
    company: { name: string; tpin: string | null };
}>();

const period = reactive({ from: props.result.from, to: props.result.to });
const onTot = props.taxRegime === 'turnover';

const showRateForm = ref(false);
const rateForm = useForm({ name: 'Turnover Tax', code: 'TOT', rate: 0, effective_from: '', effective_to: '' });

function addRate() {
    rateForm.post('/tax/turnover/rates', {
        preserveScroll: true,
        onSuccess: () => { rateForm.reset(); showRateForm.value = false; },
    });
}
function removeRate(id: number) {
    if (confirm('Remove this rate? Already-posted returns are unaffected.')) {
        router.delete(`/tax/turnover/rates/${id}`, { preserveScroll: true });
    }
}

function apply() {
    router.get('/tax/turnover', { from: period.from, to: period.to }, { preserveState: true, replace: true });
}
function post() {
    if (confirm('Post this turnover tax charge to the ledger?')) {
        router.post('/tax/turnover/post', { from: period.from, to: period.to });
    }
}
function fmt(v: number | null) {
    if (v === null) return '—';
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Turnover Tax" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-3xl mx-auto w-full">

            <div>
                <h1 class="text-2xl font-bold">Turnover Tax Return</h1>
                <p class="text-sm text-muted-foreground">{{ company.name }}<span v-if="company.tpin"> · TPIN {{ company.tpin }}</span></p>
            </div>

            <!-- Regime / rate guards -->
            <Card v-if="!onTot" class="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
                <CardContent class="pt-4 flex gap-3 text-sm">
                    <AlertTriangle class="h-5 w-5 text-amber-600 shrink-0" />
                    <p>
                        This company is on the <strong>standard</strong> regime (VAT + income tax), so turnover tax does not apply.
                        Change the tax regime in <Link href="/settings/company" class="underline">company settings</Link> if the business files TOT.
                    </p>
                </CardContent>
            </Card>

            <Card v-else-if="result.rate_error === 'none'" class="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
                <CardContent class="pt-4 flex gap-3 text-sm">
                    <AlertTriangle class="h-5 w-5 text-amber-600 shrink-0" />
                    <p>No turnover tax rate covers this period. Add one below with the current ZRA rate before filing.</p>
                </CardContent>
            </Card>

            <Card v-else-if="result.rate_error === 'ambiguous'" class="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
                <CardContent class="pt-4 flex gap-3 text-sm">
                    <AlertTriangle class="h-5 w-5 text-amber-600 shrink-0" />
                    <p>
                        This period spans a rate change, so the tax cannot be computed as one figure.
                        File <strong>separate returns</strong> for the dates either side of the change.
                    </p>
                </CardContent>
            </Card>

            <!-- Amendment warning: figures changed after this period was posted -->
            <Card v-if="result.amended" class="border-amber-300 bg-amber-50 dark:bg-amber-950/20">
                <CardContent class="pt-4 flex gap-3 text-sm">
                    <AlertTriangle class="h-5 w-5 text-amber-600 shrink-0" />
                    <p>
                        This period was posted at <strong>ZMW {{ fmt(result.posted_tax) }}</strong> but now computes as
                        <strong>ZMW {{ fmt(result.tax) }}</strong> — usually because a sale from this period was voided
                        after filing. File an amended return for the difference and post a correcting journal entry.
                    </p>
                </CardContent>
            </Card>

            <!-- Period -->
            <Card>
                <CardContent class="pt-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <div class="space-y-1">
                        <Label for="from">From</Label>
                        <Input id="from" v-model="period.from" type="date" />
                    </div>
                    <div class="space-y-1">
                        <Label for="to">To</Label>
                        <Input id="to" v-model="period.to" type="date" />
                    </div>
                    <Button @click="apply">Apply</Button>
                </CardContent>
            </Card>

            <!-- Return -->
            <Card>
                <CardHeader><CardTitle>Return for {{ result.from }} → {{ result.to }}</CardTitle></CardHeader>
                <CardContent>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between border-b pb-2">
                            <dt class="text-muted-foreground">
                                Gross turnover
                                <span class="block text-xs">Invoices issued in the period, excluding voided</span>
                            </dt>
                            <dd class="font-medium">ZMW {{ fmt(result.turnover) }}</dd>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <dt class="text-muted-foreground">Turnover tax rate</dt>
                            <dd class="font-medium">{{ result.rate !== null ? result.rate + '%' : 'Not set' }}</dd>
                        </div>
                        <div class="flex justify-between pt-1 text-base font-bold">
                            <dt>Turnover tax due</dt>
                            <dd>ZMW {{ fmt(result.tax) }}</dd>
                        </div>
                    </dl>

                    <div class="flex items-center justify-between mt-6">
                        <p v-if="result.posted" class="flex items-center gap-2 text-sm text-emerald-600">
                            <CheckCircle2 class="h-4 w-4" /> Posted to the ledger for this period.
                        </p>
                        <p v-else class="text-xs text-muted-foreground">
                            Posting creates: DR Turnover Tax Expense (8000) / CR Turnover Tax Payable (2150).
                        </p>
                        <Button v-if="onTot && !result.posted && result.tax" @click="post">Post to ledger</Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Effective-dated rates -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Turnover tax rates</CardTitle>
                    <Button size="sm" variant="outline" @click="showRateForm = !showRateForm">
                        <Plus class="mr-1 h-4 w-4" /> Add rate
                    </Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form v-if="showRateForm" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end border-b pb-4" @submit.prevent="addRate">
                        <div class="space-y-1">
                            <Label for="rname">Name</Label>
                            <Input id="rname" v-model="rateForm.name" />
                        </div>
                        <div class="space-y-1">
                            <Label for="rcode">Code</Label>
                            <Input id="rcode" v-model="rateForm.code" />
                        </div>
                        <div class="space-y-1">
                            <Label for="rrate">Rate %</Label>
                            <Input id="rrate" v-model.number="rateForm.rate" type="number" min="0" max="100" step="0.01" />
                        </div>
                        <div class="space-y-1">
                            <Label for="rfrom">Effective from</Label>
                            <Input id="rfrom" v-model="rateForm.effective_from" type="date" />
                        </div>
                        <div class="space-y-1">
                            <Label for="rto">Effective to</Label>
                            <Input id="rto" v-model="rateForm.effective_to" type="date" />
                        </div>
                        <div class="md:col-span-5 flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="showRateForm = false">Cancel</Button>
                            <Button type="submit" :disabled="rateForm.processing">Save rate</Button>
                        </div>
                    </form>

                    <div v-for="r in rates" :key="r.id" class="flex items-center gap-3 text-sm group">
                        <span class="font-medium">{{ r.rate }}%</span>
                        <span class="text-muted-foreground">{{ r.name }}</span>
                        <Badge variant="outline" class="text-[10px] py-0">{{ r.period }}</Badge>
                        <button type="button" class="ml-auto opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive"
                            @click="removeRate(r.id)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <p v-if="!rates.length" class="text-sm text-muted-foreground">
                        No rates configured. Leave both dates blank for a rate that always applies.
                    </p>
                </CardContent>
            </Card>

            <p class="text-xs text-muted-foreground">
                Turnover tax is a final tax charged in lieu of income tax. Rates and the eligibility threshold are set
                by each annual Finance Act — confirm the current figures against ZRA guidance before filing.
                Dating each rate keeps historical returns computing at the rate that applied then.
            </p>
        </div>
    </AppLayout>
</template>
