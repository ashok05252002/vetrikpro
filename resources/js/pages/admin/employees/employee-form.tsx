import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { Department, Designation, Option } from '@/types';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';
import { employmentTypeLabels, statusLabels } from './labels';

const NONE = 'none';

// A type alias, not an interface: useForm needs an implicit index signature.
// The person's login (name, email) and HR record are one form.
export type FormData = {
    name: string;
    email: string;
    /** Create only: the role, the invite and the offer letter. */
    role_id: string;
    send_invite: boolean;
    /** Generate from the template in Configuration hub, upload a file, or none. */
    offer_letter_mode: 'generate' | 'welcome' | 'internship' | 'upload' | 'none';
    offer_letter: File | null;
    employee_code: string;
    department_id: string;
    designation_id: string;
    phone: string;
    date_of_birth: string;
    gender: string;
    date_of_joining: string;
    employment_type: string;
    salary: string;
    /** Interns only: whether a stipend is paid, and how much a month. */
    has_stipend?: boolean;
    stipend?: string;
    address: string;
    status: string;
};

interface Props {
    departments: Department[];
    designations: Designation[];
    /** Only the roles the signed-in person may give. On an edit, empty when they may not change access. */
    roles?: Option[];
    /** Edit only: the current role, shown when it cannot be changed here. */
    roleName?: string | null;
    initial: FormData;
    action: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    creating?: boolean;
    /** Intern mode: no role, employment type, salary or offer letter; a stipend instead. */
    intern?: boolean;
    cancelHref?: string;
}

export default function EmployeeForm({
    departments,
    designations,
    roles = [],
    roleName = null,
    initial,
    action,
    submitLabel,
    creating = false,
    intern = false,
    cancelHref,
}: Props) {
    const { data, setData, post, put, processing, errors, transform } = useForm<FormData>(initial);

    // Radix Select has no empty-string value, so optional relations ride as a
    // sentinel and are blanked on the way to the server.
    transform((payload) => {
        const cleaned = {
            ...payload,
            department_id: payload.department_id === NONE ? '' : payload.department_id,
            designation_id: payload.designation_id === NONE ? '' : payload.designation_id,
            gender: payload.gender === NONE ? '' : payload.gender,
        };

        // Invite and offer letter are chosen once, at creation; the role on an
        // edit only by someone who may change access. The server refuses the
        // rest on an edit, so they are not sent.
        if (!creating) {
            const { role_id: _r, send_invite: _s, offer_letter: _o, offer_letter_mode: _m, ...rest } = cleaned;
            void [_s, _o, _m];
            return (roles.length > 0 && !intern ? { ...rest, role_id: _r } : rest) as typeof cleaned;
        }

        return cleaned;
    });

    // Only show designations belonging to the chosen department (plus unscoped ones).
    const visibleDesignations = useMemo(() => {
        if (data.department_id === NONE) {
            return designations;
        }
        return designations.filter((d) => d.department_id === null || String(d.department_id) === data.department_id);
    }, [designations, data.department_id]);

    const onDepartmentChange = (value: string) => {
        setData((current) => ({
            ...current,
            department_id: value,
            // Drop a designation that no longer belongs to the selected department.
            designation_id: designations.some(
                (d) => String(d.id) === current.designation_id && (d.department_id === null || String(d.department_id) === value),
            )
                ? current.designation_id
                : NONE,
        }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        (action.method === 'put' ? put : post)(action.url, { preserveScroll: true, forceFormData: creating });
    };

    return (
        <form onSubmit={submit} className="max-w-3xl space-y-6">
            <div className="grid gap-6 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">Full name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus
                        placeholder="Priya Raman"
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="off"
                        placeholder="priya@company.com"
                    />
                    <InputError message={errors.email} />
                    <p className="text-muted-foreground text-xs">
                        {creating
                            ? 'They sign in with this and receive the invite here.'
                            : 'Their sign-in email. Changing it changes how they log in.'}
                    </p>
                </div>

                {!creating && !intern && roles.length === 0 && (
                    <div className="grid gap-2">
                        <Label>Role</Label>
                        <p className="flex h-9 items-center text-sm">{roleName ?? '—'}</p>
                        <p className="text-muted-foreground text-xs">Changing someone&rsquo;s role needs permission to edit access.</p>
                    </div>
                )}

                {(creating || roles.length > 0) && !intern && (
                    <div className="grid gap-2">
                        <Label htmlFor="role_id">Role</Label>
                        <Select value={data.role_id} onValueChange={(value) => setData('role_id', value)}>
                            <SelectTrigger id="role_id">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role.value} value={role.value}>
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role_id} />
                        <p className="text-muted-foreground text-xs">
                            What they can do in the portal. {creating ? 'Fine-tune later on their Access tab.' : 'Fine-tune on their Access tab.'}
                        </p>
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="employee_code">{intern ? 'Intern code' : 'Employee code'}</Label>
                    <Input id="employee_code" value={data.employee_code} onChange={(e) => setData('employee_code', e.target.value)} required />
                    <InputError message={errors.employee_code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="department_id">Department</Label>
                    <Select value={data.department_id} onValueChange={onDepartmentChange}>
                        <SelectTrigger id="department_id">
                            <SelectValue placeholder="Select a department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>No department</SelectItem>
                            {departments.map((department) => (
                                <SelectItem key={department.id} value={String(department.id)}>
                                    {department.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.department_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="designation_id">Designation</Label>
                    <Select value={data.designation_id} onValueChange={(value) => setData('designation_id', value)}>
                        <SelectTrigger id="designation_id">
                            <SelectValue placeholder="Select a designation" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>No designation</SelectItem>
                            {visibleDesignations.map((designation) => (
                                <SelectItem key={designation.id} value={String(designation.id)}>
                                    {designation.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.designation_id} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="phone">Phone</Label>
                    <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="+91 98765 43210" />
                    <InputError message={errors.phone} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="gender">Gender</Label>
                    <Select value={data.gender} onValueChange={(value) => setData('gender', value)}>
                        <SelectTrigger id="gender">
                            <SelectValue placeholder="Not specified" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>Not specified</SelectItem>
                            <SelectItem value="male">Male</SelectItem>
                            <SelectItem value="female">Female</SelectItem>
                            <SelectItem value="other">Other</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="date_of_birth">Date of birth</Label>
                    <DatePicker id="date_of_birth" value={data.date_of_birth ?? ''} onChange={(v) => setData('date_of_birth', v)} notAfterToday />
                    <InputError message={errors.date_of_birth} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="date_of_joining">
                        {intern ? 'Internship starts' : 'Date of joining'}{' '}
                        {creating && ['generate', 'welcome', 'internship'].includes(data.offer_letter_mode) && (
                            <span className="text-destructive">*</span>
                        )}
                    </Label>
                    <DatePicker id="date_of_joining" value={data.date_of_joining ?? ''} onChange={(v) => setData('date_of_joining', v)} />
                    <InputError message={errors.date_of_joining} />
                </div>

                {!intern && (
                    <div className="grid gap-2">
                        <Label htmlFor="employment_type">Employment type</Label>
                        <Select value={data.employment_type} onValueChange={(value) => setData('employment_type', value)}>
                            <SelectTrigger id="employment_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(employmentTypeLabels)
                                    // Interns are added from the Interns page.
                                    .filter(([value]) => value !== 'intern' || initial.employment_type === 'intern')
                                    .map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.employment_type} />
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger id="status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(statusLabels).map(([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>

                {!intern && (
                    <div className="grid gap-2">
                        <Label htmlFor="salary">
                            Monthly salary {creating && data.offer_letter_mode === 'generate' && <span className="text-destructive">*</span>}
                        </Label>
                        <Input
                            id="salary"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.salary}
                            onChange={(e) => setData('salary', e.target.value)}
                        />
                        <InputError message={errors.salary} />
                    </div>
                )}
            </div>

            {intern && (
                <section className="space-y-3 rounded-xl border p-4">
                    <div>
                        <h2 className="text-sm font-semibold">Stipend</h2>
                        <p className="text-muted-foreground text-xs">Is this internship paid?</p>
                    </div>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={data.has_stipend ? 'yes' : 'no'}
                        onValueChange={(value) =>
                            value && setData((d) => ({ ...d, has_stipend: value === 'yes', stipend: value === 'yes' ? d.stipend : '' }))
                        }
                        className="justify-start"
                    >
                        <ToggleGroupItem value="yes" className="px-4 text-xs">
                            Yes, a stipend
                        </ToggleGroupItem>
                        <ToggleGroupItem value="no" className="px-4 text-xs">
                            No stipend
                        </ToggleGroupItem>
                    </ToggleGroup>
                    {data.has_stipend && (
                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="stipend">Monthly stipend</Label>
                            <Input
                                id="stipend"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                autoFocus
                                value={data.stipend ?? ''}
                                onChange={(e) => setData('stipend', e.target.value)}
                                placeholder="10000"
                            />
                        </div>
                    )}
                    <InputError message={errors.stipend} />
                </section>
            )}

            <div className="grid gap-2">
                <Label htmlFor="address">Address</Label>
                <Textarea id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                <InputError message={errors.address} />
            </div>

            {creating && intern && (
                <section className="space-y-4 rounded-xl border p-4">
                    <div>
                        <h2 className="text-sm font-semibold">Invite and internship letter</h2>
                        <p className="text-muted-foreground text-xs">
                            They set a password, then complete onboarding: details, bank account, documents and the signed internship letter.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label>Internship letter</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={data.offer_letter_mode}
                            onValueChange={(value) => value && setData('offer_letter_mode', value as FormData['offer_letter_mode'])}
                            className="flex-wrap justify-start"
                        >
                            <ToggleGroupItem value="internship" className="px-3 text-xs">
                                {data.has_stipend ? 'Internship letter with stipend' : 'Internship letter, no stipend'}
                            </ToggleGroupItem>
                            <ToggleGroupItem value="upload" className="px-3 text-xs">
                                Upload my own
                            </ToggleGroupItem>
                            <ToggleGroupItem value="none" className="px-3 text-xs">
                                No letter
                            </ToggleGroupItem>
                        </ToggleGroup>

                        {data.offer_letter_mode === 'internship' && (
                            <p className="text-muted-foreground text-xs">
                                A PDF with your logo, from the{' '}
                                <Link href={route('admin.config.offer-letter.edit')} className="underline" target="_blank">
                                    internship letter template
                                </Link>
                                . {data.has_stipend ? 'It states the monthly stipend above.' : 'It says the internship is unpaid.'} The start date is
                                required.
                            </p>
                        )}

                        {data.offer_letter_mode === 'upload' && (
                            <Input
                                id="offer_letter"
                                type="file"
                                accept=".pdf,.doc,.docx"
                                onChange={(e) => setData('offer_letter', e.target.files?.[0] ?? null)}
                            />
                        )}
                        <InputError message={errors.offer_letter} />
                    </div>

                    <label className="flex items-start gap-3">
                        <Checkbox
                            className="mt-0.5"
                            checked={data.send_invite}
                            onCheckedChange={(checked) => setData('send_invite', checked === true)}
                        />
                        <span className="text-sm">
                            Send the invite email now
                            <span className="text-muted-foreground block text-xs">The letter is attached. Leave unticked to send it later.</span>
                        </span>
                    </label>
                </section>
            )}

            {creating && !intern && (
                <section className="space-y-4 rounded-xl border p-4">
                    <div>
                        <h2 className="text-sm font-semibold">Invite and offer letter</h2>
                        <p className="text-muted-foreground text-xs">
                            The invite asks them to set a password, then add bank details, upload their documents and return the signed offer letter.
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label>Offer letter</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={data.offer_letter_mode}
                            onValueChange={(value) => value && setData('offer_letter_mode', value as FormData['offer_letter_mode'])}
                            className="flex-wrap justify-start"
                        >
                            <ToggleGroupItem value="generate" className="px-3 text-xs">
                                Offer letter with salary
                            </ToggleGroupItem>
                            <ToggleGroupItem value="welcome" className="px-3 text-xs">
                                Welcome letter, no salary
                            </ToggleGroupItem>
                            <ToggleGroupItem value="upload" className="px-3 text-xs">
                                Upload my own
                            </ToggleGroupItem>
                            <ToggleGroupItem value="none" className="px-3 text-xs">
                                No offer letter
                            </ToggleGroupItem>
                        </ToggleGroup>

                        {data.offer_letter_mode === 'generate' && (
                            <p className="text-muted-foreground text-xs">
                                A PDF with your logo is made from the{' '}
                                <Link href={route('admin.config.offer-letter.edit')} className="underline" target="_blank">
                                    offer letter template
                                </Link>
                                , using the designation, date of joining and monthly salary above — so the date and salary are required.
                            </p>
                        )}

                        {data.offer_letter_mode === 'welcome' && (
                            <p className="text-muted-foreground text-xs">
                                A greeting letter with your logo, like the offer letter but with no package details — for contract staff and anyone
                                whose pay you don&rsquo;t send. Salary is optional. Wording is under{' '}
                                <Link href={route('admin.config.offer-letter.edit')} className="underline" target="_blank">
                                    offer letter template
                                </Link>
                                .
                            </p>
                        )}

                        {data.offer_letter_mode === 'upload' && (
                            <Input
                                id="offer_letter"
                                type="file"
                                accept=".pdf,.doc,.docx"
                                onChange={(e) => setData('offer_letter', e.target.files?.[0] ?? null)}
                            />
                        )}

                        {data.offer_letter_mode !== 'none' && (
                            <p className="text-muted-foreground text-xs">
                                Attached to the invite email. They’ll be asked to sign it and upload the signed copy.
                            </p>
                        )}
                        <InputError message={errors.offer_letter} />
                    </div>

                    <label className="flex items-start gap-3">
                        <Checkbox
                            className="mt-0.5"
                            checked={data.send_invite}
                            onCheckedChange={(checked) => setData('send_invite', checked === true)}
                        />
                        <span className="text-sm">
                            Send the invite email now
                            <span className="text-muted-foreground block text-xs">Leave unticked to send it later from their Onboarding tab.</span>
                        </span>
                    </label>
                </section>
            )}

            <div className="flex items-center gap-3">
                <Button disabled={processing}>{submitLabel}</Button>
                <Button asChild variant="ghost" type="button">
                    <Link href={cancelHref ?? route('admin.employees.index')}>Cancel</Link>
                </Button>
            </div>
        </form>
    );
}

export { NONE };
