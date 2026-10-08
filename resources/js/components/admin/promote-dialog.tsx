import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import type { Department, Designation } from '@/types';
import { useForm } from '@inertiajs/react';
import { Mail, TrendingUp } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Props {
    employee: {
        id: number;
        name: string;
        email: string;
        designation_id: number | null;
        department_id: number | null;
        salary: string | null;
    };
    departments: Pick<Department, 'id' | 'name'>[];
    designations: Pick<Designation, 'id' | 'name' | 'department_id'>[];
    /** False when the viewer's role cannot see pay: the dialog changes the designation only and the salary carries over. */
    canSetPay: boolean;
}

type PromotionForm = {
    to_designation_id: string;
    to_department_id: string;
    to_salary: string;
    effective_date: string;
    note: string;
    send_email: boolean;
};

const today = () => new Date().toISOString().slice(0, 10);
const round2 = (n: number) => Math.round(n * 100) / 100;

/**
 * Promote someone or revise their salary. The raise can be typed as a new
 * salary or as a percentage — each fills in the other — and the letter goes
 * out by email unless unticked. Without the salary permission only the
 * designation changes, and no pay is shown or sent.
 */
export default function PromoteDialog({ employee, departments, designations, canSetPay }: Props) {
    const format = useFormat();
    const [open, setOpen] = useState(false);
    const current = employee.salary !== null ? Number(employee.salary) : null;

    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<PromotionForm>({
        to_designation_id: employee.designation_id ? String(employee.designation_id) : '',
        to_department_id: employee.department_id ? String(employee.department_id) : '',
        to_salary: employee.salary ?? '',
        effective_date: today(),
        note: '',
        send_email: true,
    });

    const [percent, setPercent] = useState('');

    const pickDesignation = (value: string) => {
        const designation = designations.find((d) => String(d.id) === value);
        setData((form) => ({
            ...form,
            to_designation_id: value,
            // A designation that belongs to a department brings them into it.
            to_department_id: designation?.department_id ? String(designation.department_id) : form.to_department_id,
        }));
    };

    const typeSalary = (value: string) => {
        setData('to_salary', value);
        const next = Number(value);
        setPercent(current && value !== '' && !Number.isNaN(next) ? String(round2(((next - current) / current) * 100)) : '');
    };

    const typePercent = (value: string) => {
        setPercent(value);
        const pct = Number(value);
        if (current !== null && value !== '' && !Number.isNaN(pct)) {
            setData('to_salary', String(round2(current * (1 + pct / 100))));
        }
    };

    const newSalary = Number(data.to_salary);
    const increase = current !== null && data.to_salary !== '' && !Number.isNaN(newSalary) ? newSalary - current : null;
    const promoted = data.to_designation_id !== '' && data.to_designation_id !== String(employee.designation_id ?? '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((form) => {
            if (canSetPay) {
                return form;
            }

            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const { to_salary, ...rest } = form;
            return rest;
        });
        post(route('admin.employees.promotions.store', employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
                setPercent('');
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) {
                    clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm">
                    <TrendingUp className="size-4" /> {canSetPay ? 'Promote / revise salary' : 'Promote'}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>
                            {promoted || !canSetPay
                                ? `Promote ${employee.name}`
                                : data.to_designation_id
                                  ? `Revise ${employee.name}'s salary`
                                  : `Promote ${employee.name} or revise their salary`}
                        </DialogTitle>
                        <DialogDescription>
                            Their profile changes as soon as you save. A letter is generated and kept on their profile.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="to_designation_id">New designation</Label>
                            <Select value={data.to_designation_id} onValueChange={pickDesignation}>
                                <SelectTrigger id="to_designation_id">
                                    <SelectValue placeholder="Choose a designation" />
                                </SelectTrigger>
                                <SelectContent>
                                    {designations.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.name}
                                            {d.id === employee.designation_id && ' (current)'}
                                            {d.department_id && ` — ${departments.find((dep) => dep.id === d.department_id)?.name ?? ''}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {canSetPay && <p className="text-muted-foreground text-xs">Keep the current one to revise the salary only.</p>}
                            <InputError message={errors.to_designation_id} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="to_department_id">Department</Label>
                            <Select value={data.to_department_id} onValueChange={(value) => setData('to_department_id', value)}>
                                <SelectTrigger id="to_department_id">
                                    <SelectValue placeholder="No department" />
                                </SelectTrigger>
                                <SelectContent>
                                    {departments.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.to_department_id} />
                        </div>

                        {canSetPay && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="to_salary">New monthly salary</Label>
                                    <Input
                                        id="to_salary"
                                        type="number"
                                        inputMode="decimal"
                                        min={0}
                                        step="0.01"
                                        required
                                        value={data.to_salary}
                                        onChange={(e) => typeSalary(e.target.value)}
                                    />
                                    <InputError message={errors.to_salary} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="percent">Increase %</Label>
                                    <Input
                                        id="percent"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.1"
                                        value={percent}
                                        onChange={(e) => typePercent(e.target.value)}
                                        disabled={!current}
                                        placeholder={current ? 'e.g. 15' : 'No current salary'}
                                    />
                                </div>

                                <p className="text-muted-foreground -mt-2 text-xs sm:col-span-2">
                                    Now {current !== null ? format.money(current) : 'not set'}
                                    {increase !== null && increase !== 0 && (
                                        <span
                                            className={
                                                increase > 0 ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'text-destructive font-medium'
                                            }
                                        >
                                            {' '}
                                            → {format.money(newSalary)} ({increase > 0 ? '+' : ''}
                                            {format.money(increase)} a month)
                                        </span>
                                    )}
                                </p>
                            </>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="effective_date">Effective from</Label>
                            <DatePicker
                                id="effective_date"
                                value={data.effective_date ?? ''}
                                onChange={(v) => setData('effective_date', v)}
                                required
                            />
                            <InputError message={errors.effective_date} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="note">
                                Internal note <span className="text-muted-foreground">(optional, not in the letter)</span>
                            </Label>
                            <Textarea
                                id="note"
                                rows={2}
                                value={data.note}
                                onChange={(e) => setData('note', e.target.value)}
                                placeholder="Why, e.g. annual appraisal 2026"
                            />
                            <InputError message={errors.note} />
                        </div>

                        <label className="flex items-start gap-3 text-sm sm:col-span-2">
                            <Checkbox
                                checked={data.send_email}
                                onCheckedChange={(checked) => setData('send_email', checked === true)}
                                className="mt-0.5"
                            />
                            <span className="inline-flex items-center gap-1.5">
                                <Mail className="size-4" style={{ color: 'var(--tone-blue)' }} aria-hidden />
                                Email the letter to <span className="font-medium">{employee.email}</span>
                            </span>
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button disabled={processing}>{promoted || !canSetPay ? 'Promote' : 'Revise salary'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
