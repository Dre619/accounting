export type Company = {
    id: number;
    name: string;
    tpin: string | null;
    vat_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string;
    currency: string;
    financial_year_end: string;
    invoice_prefix: string;
    invoice_sequence: number;
    logo_path: string | null;
    created_at: string;
    updated_at: string;
};

export type TaxRate = {
    id: number;
    company_id: number;
    name: string;
    code: string;
    type: 'vat' | 'withholding' | 'other';
    rate: string;
    is_compound: boolean;
    is_active: boolean;
};

export type AccountCategory = {
    id: number;
    company_id: number | null;
    name: string;
    type: AccountType;
    sort_order: number;
};

export type AccountType = 'asset' | 'liability' | 'equity' | 'income' | 'expense';

export type Account = {
    id: number;
    company_id: number;
    account_category_id: number;
    parent_id: number | null;
    code: string;
    name: string;
    description: string | null;
    type: AccountType;
    subtype: string | null;
    is_bank_account: boolean;
    is_system: boolean;
    is_active: boolean;
    opening_balance: string;
    opening_balance_date: string | null;
    category?: AccountCategory;
};

export type Contact = {
    id: number;
    company_id: number;
    type: 'customer' | 'supplier' | 'both';
    name: string;
    tpin: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string;
    withholding_tax_applicable: boolean;
    is_active: boolean;
    notes: string | null;
};

export type InvoiceStatus = 'draft' | 'sent' | 'partial' | 'paid' | 'overdue' | 'void';

export type InvoiceItem = {
    id?: number;
    account_id: number | null;
    tax_rate_id: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_percent: number;
    subtotal: number;
    tax_amount: number;
    total: number;
    sort_order: number;
    tax_rate?: TaxRate;
    account?: Account;
};

export type Invoice = {
    id: number;
    company_id: number;
    contact_id: number;
    invoice_number: string;
    status: InvoiceStatus;
    issue_date: string;
    due_date: string;
    reference: string | null;
    notes: string | null;
    footer: string | null;
    subtotal: string;
    tax_amount: string;
    withholding_tax_amount: string;
    discount_amount: string;
    total: string;
    amount_paid: string;
    amount_due: string;
    contact?: Contact;
    items?: InvoiceItem[];
    created_at: string;
    updated_at: string;
};

export type BillStatus = 'draft' | 'approved' | 'partial' | 'paid' | 'overdue' | 'void';

export type Bill = {
    id: number;
    company_id: number;
    contact_id: number;
    bill_number: string | null;
    reference: string | null;
    status: BillStatus;
    issue_date: string;
    due_date: string;
    notes: string | null;
    subtotal: string;
    tax_amount: string;
    withholding_tax_amount: string;
    discount_amount: string;
    total: string;
    amount_paid: string;
    amount_due: string;
    contact?: Contact;
    created_at: string;
    updated_at: string;
};

export type PaymentMethod = 'cash' | 'bank_transfer' | 'cheque' | 'airtel_money' | 'mtn_money' | 'zamtel_money' | 'other';

export type Payment = {
    id: number;
    company_id: number;
    contact_id: number | null;
    type: 'receipt' | 'payment';
    payment_number: string | null;
    payment_date: string;
    amount: string;
    withholding_tax_amount: string;
    method: PaymentMethod;
    reference: string | null;
    deposit_account_id: number;
    notes: string | null;
    contact?: Contact;
    deposit_account?: Account;
    created_at: string;
    updated_at: string;
};
