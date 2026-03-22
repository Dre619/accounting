<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZraVsdcService
{
    /**
     * Initialize the VSDC device for a company.
     * Calls POST /initializer/selectInitInfo and stores the returned SDC ID + MRC number.
     *
     * @return array The raw `data` block from the VSDC response
     * @throws RuntimeException on HTTP failure or non-000 result code
     */
    public function initialize(Company $company): array
    {
        $this->assertConfigured($company);

        $response = $this->post($company, '/initializer/selectInitInfo', [
            'tpin'       => $company->tpin,
            'bhfId'      => $company->vsdc_bhf_id,
            'dvcSrlNo'   => $company->vsdc_dvc_srl_no ?? '',
        ]);

        $data = $response['data'] ?? [];

        $company->update([
            'vsdc_initialized' => true,
            'vsdc_sdc_id'      => $data['sdcId']  ?? null,
            'vsdc_mrc_no'      => $data['mrcNo']  ?? null,
        ]);

        return $data;
    }

    /**
     * Submit a sales invoice to ZRA via the VSDC.
     * Calls POST /trnsSales/saveSales and stores the receipt data on the invoice.
     *
     * @return array The raw `data` block from the VSDC response
     * @throws RuntimeException on HTTP failure or non-000 result code
     */
    public function submitSale(Invoice $invoice): array
    {
        $company = $invoice->company;
        $this->assertConfigured($company);

        $payload = $this->buildSalePayload($invoice, $company);

        $response = $this->post($company, '/trnsSales/saveSales', $payload);

        $data = $response['data'] ?? [];

        $invoice->update([
            'zra_submitted_at' => now(),
            'zra_rcpt_no'      => $data['rcptNo']     ?? null,
            'zra_internal_data'=> $data['intrlData']  ?? null,
            'zra_rcpt_sign'    => $data['rcptSign']   ?? null,
            'zra_sdc_id'       => $data['sdcId']      ?? null,
            'zra_mrc_no'       => $data['mrcNo']      ?? null,
        ]);

        return $data;
    }

    /**
     * Submit a purchase (bill) to ZRA via the VSDC.
     * Calls POST /trnsPurchase/savePurchase and stores the confirmation number on the bill.
     *
     * @return array The raw `data` block from the VSDC response
     * @throws RuntimeException on HTTP failure or non-000 result code
     */
    public function submitPurchase(Bill $bill): array
    {
        $company = $bill->company;
        $this->assertConfigured($company);

        $payload = $this->buildPurchasePayload($bill, $company);

        $response = $this->post($company, '/trnsPurchase/savePurchase', $payload);

        $data = $response['data'] ?? [];

        $bill->update([
            'zra_submitted_at'    => now(),
            'zra_confirmation_no' => $data['rcptNo'] ?? ($data['confirmationNo'] ?? null),
        ]);

        return $data;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildSalePayload(Invoice $invoice, Company $company): array
    {
        $items     = $invoice->items->load(['taxRate', 'goodsCode', 'serviceCode']);
        $issueDt   = $invoice->issue_date->format('Ymd');
        $confirmDt = now()->format('YmdHis');

        // Aggregate taxable + tax amounts per ZRA tax category
        $taxBuckets = ['A' => ['taxbl' => 0.0, 'tax' => 0.0],
                       'B' => ['taxbl' => 0.0, 'tax' => 0.0],
                       'C1'=> ['taxbl' => 0.0, 'tax' => 0.0],
                       'C2'=> ['taxbl' => 0.0, 'tax' => 0.0],
                       'C3'=> ['taxbl' => 0.0, 'tax' => 0.0],
                       'D' => ['taxbl' => 0.0, 'tax' => 0.0]];

        $itemList = [];
        foreach ($items as $seq => $item) {
            $taxCd  = $this->mapTaxType($item->taxRate?->rate);
            $taxbl  = (float) $item->subtotal;
            $taxAmt = (float) $item->tax_amount;

            $taxBuckets[$taxCd]['taxbl'] += $taxbl;
            $taxBuckets[$taxCd]['tax']   += $taxAmt;

            $itemList[] = [
                'itemSeq'       => $seq + 1,
                'itemCd'        => $this->itemCode($seq + 1),
                'itemClsCd'     => $item->itemClsCd() ?? '5020230100',
                'itemNm'        => mb_substr($item->description, 0, 100),
                'bcd'           => null,
                'pkgUnitCd'     => 'NT',
                'pkg'           => 1,
                'qtyUnitCd'     => 'BA',
                'qty'           => (float) $item->quantity,
                'prc'           => (float) $item->unit_price,
                'splyAmt'       => $taxbl,
                'dcRt'          => (float) $item->discount_percent,
                'dcAmt'         => round((float) $item->quantity * (float) $item->unit_price * ((float) $item->discount_percent / 100), 2),
                'isrccCd'       => null,
                'isrccNm'       => null,
                'isrcRt'        => null,
                'isrcAmt'       => null,
                'vatCatCd'      => $taxCd,
                'exciseTxCatCd' => null,
                'taxblAmt'      => $taxbl,
                'taxAmt'        => $taxAmt,
                'totAmt'        => (float) $item->total,
            ];
        }

        $totTaxblAmt = array_sum(array_column($taxBuckets, 'taxbl'));
        $totTaxAmt   = array_sum(array_column($taxBuckets, 'tax'));

        return [
            'tpin'         => $company->tpin,
            'bhfId'        => $company->vsdc_bhf_id,
            'orgInvcNo'    => 0,
            'cisInvcNo'    => $invoice->invoice_number,
            'custTpin'     => $invoice->contact?->tpin,
            'custNm'       => $invoice->contact?->name,
            'salesTyCd'    => 'N',
            'rcptTyCd'     => 'S',
            'pmtTyCd'      => '01',   // cash; extend later if needed
            'salesSttsCd'  => '02',   // approved
            'cfmDt'        => $confirmDt,
            'salesDt'      => $issueDt,
            'stockRlsDt'   => null,
            'cnclReqDt'    => null,
            'cnclDt'       => null,
            'rfdDt'        => null,
            'rfdRsnCd'     => null,
            'totItemCnt'   => count($itemList),
            'taxblAmtA'    => $taxBuckets['A']['taxbl'],
            'taxblAmtB'    => $taxBuckets['B']['taxbl'],
            'taxblAmtC1'   => $taxBuckets['C1']['taxbl'],
            'taxblAmtC2'   => $taxBuckets['C2']['taxbl'],
            'taxblAmtC3'   => $taxBuckets['C3']['taxbl'],
            'taxblAmtD'    => $taxBuckets['D']['taxbl'],
            'taxblAmtRvat' => 0,
            'taxblAmtTot'  => 0,
            'taxblAmtE'    => 0,
            'taxblAmtF'    => 0,
            'taxRtA'       => 16.0,
            'taxRtB'       => 16.0,
            'taxRtC1'      => 0.0,
            'taxRtC2'      => 0.0,
            'taxRtC3'      => 0.0,
            'taxRtD'       => 0.0,
            'taxRtRvat'    => 16.0,
            'taxRtTot'     => 0.0,
            'taxRtE'       => 0.0,
            'taxRtF'       => 0.0,
            'taxAmtA'      => $taxBuckets['A']['tax'],
            'taxAmtB'      => $taxBuckets['B']['tax'],
            'taxAmtC1'     => $taxBuckets['C1']['tax'],
            'taxAmtC2'     => $taxBuckets['C2']['tax'],
            'taxAmtC3'     => $taxBuckets['C3']['tax'],
            'taxAmtD'      => $taxBuckets['D']['tax'],
            'taxAmtRvat'   => 0,
            'taxAmtTot'    => 0,
            'taxAmtE'      => 0,
            'taxAmtF'      => 0,
            'totTaxblAmt'  => $totTaxblAmt,
            'totTaxAmt'    => $totTaxAmt,
            'totAmt'       => (float) $invoice->total,
            'prchrAcptcYn' => 'N',
            'remark'       => $invoice->notes ? mb_substr($invoice->notes, 0, 200) : null,
            'regrId'       => 'admin',
            'regrNm'       => $company->name,
            'modrId'       => 'admin',
            'modrNm'       => $company->name,
            'receipt'      => [
                'custTpin'     => $invoice->contact?->tpin,
                'custMblNo'    => null,
                'rptNo'        => 1,
                'trdeNm'       => $company->name,
                'adrs'         => $company->address,
                'topMsg'       => 'Thank you for your business',
                'btmMsg'       => $invoice->footer ?? '',
                'prchrAcptcYn' => 'N',
            ],
            'itemList' => $itemList,
        ];
    }

    private function buildPurchasePayload(Bill $bill, Company $company): array
    {
        $items     = $bill->items->load(['taxRate', 'goodsCode', 'serviceCode']);
        $issueDt   = $bill->issue_date->format('Ymd');
        $confirmDt = now()->format('YmdHis');

        $taxBuckets = ['A' => ['taxbl' => 0.0, 'tax' => 0.0],
                       'B' => ['taxbl' => 0.0, 'tax' => 0.0],
                       'C1'=> ['taxbl' => 0.0, 'tax' => 0.0],
                       'C2'=> ['taxbl' => 0.0, 'tax' => 0.0],
                       'C3'=> ['taxbl' => 0.0, 'tax' => 0.0],
                       'D' => ['taxbl' => 0.0, 'tax' => 0.0]];

        $itemList = [];
        foreach ($items as $seq => $item) {
            $taxCd  = $this->mapTaxType($item->taxRate?->rate);
            $taxbl  = (float) $item->subtotal;
            $taxAmt = (float) $item->tax_amount;

            $taxBuckets[$taxCd]['taxbl'] += $taxbl;
            $taxBuckets[$taxCd]['tax']   += $taxAmt;

            $itemList[] = [
                'itemSeq'       => $seq + 1,
                'itemCd'        => $this->itemCode($seq + 1),
                'itemClsCd'     => $item->itemClsCd() ?? '5020230100',
                'itemNm'        => mb_substr($item->description, 0, 100),
                'bcd'           => null,
                'pkgUnitCd'     => 'NT',
                'pkg'           => 1,
                'qtyUnitCd'     => 'BA',
                'qty'           => (float) $item->quantity,
                'prc'           => (float) $item->unit_price,
                'splyAmt'       => $taxbl,
                'dcRt'          => (float) $item->discount_percent,
                'dcAmt'         => round((float) $item->quantity * (float) $item->unit_price * ((float) $item->discount_percent / 100), 2),
                'taxblAmt'      => $taxbl,
                'taxAmt'        => $taxAmt,
                'totAmt'        => (float) $item->total,
            ];
        }

        $totTaxblAmt = array_sum(array_column($taxBuckets, 'taxbl'));
        $totTaxAmt   = array_sum(array_column($taxBuckets, 'tax'));

        return [
            'tpin'         => $company->tpin,
            'bhfId'        => $company->vsdc_bhf_id,
            'orgInvcNo'    => 0,
            'spplrTpin'    => $bill->contact?->tpin,
            'spplrNm'      => $bill->contact?->name,
            'spplrInvcNo'  => $bill->bill_number,
            'regTyCd'      => 'M',
            'pchsTyCd'     => 'N',
            'rcptTyCd'     => 'P',
            'pmtTyCd'      => '01',
            'pchsSttsCd'   => '02',
            'cfmDt'        => $confirmDt,
            'pchsDt'       => $issueDt,
            'totItemCnt'   => count($itemList),
            'taxblAmtA'    => $taxBuckets['A']['taxbl'],
            'taxblAmtB'    => $taxBuckets['B']['taxbl'],
            'taxblAmtC1'   => $taxBuckets['C1']['taxbl'],
            'taxblAmtC2'   => $taxBuckets['C2']['taxbl'],
            'taxblAmtC3'   => $taxBuckets['C3']['taxbl'],
            'taxblAmtD'    => $taxBuckets['D']['taxbl'],
            'taxRtA'       => 16.0,
            'taxRtB'       => 16.0,
            'taxRtC1'      => 0.0,
            'taxRtC2'      => 0.0,
            'taxRtC3'      => 0.0,
            'taxRtD'       => 0.0,
            'taxAmtA'      => $taxBuckets['A']['tax'],
            'taxAmtB'      => $taxBuckets['B']['tax'],
            'taxAmtC1'     => $taxBuckets['C1']['tax'],
            'taxAmtC2'     => $taxBuckets['C2']['tax'],
            'taxAmtC3'     => $taxBuckets['C3']['tax'],
            'taxAmtD'      => $taxBuckets['D']['tax'],
            'totTaxblAmt'  => $totTaxblAmt,
            'totTaxAmt'    => $totTaxAmt,
            'totAmt'       => (float) $bill->total,
            'remark'       => $bill->notes ? mb_substr($bill->notes, 0, 200) : null,
            'regrId'       => 'admin',
            'regrNm'       => $company->name,
            'modrId'       => 'admin',
            'modrNm'       => $company->name,
            'itemList'     => $itemList,
        ];
    }

    /**
     * Map a tax rate percentage to a ZRA VAT category code.
     *  16%        → A (Standard Rate)
     *  0%         → C3 (Zero-rated by nature)
     *  null/other → D (Exempt)
     */
    private function mapTaxType(?string $rate): string
    {
        if ($rate === null) {
            return 'D';
        }
        return match ((int) round((float) $rate)) {
            16 => 'A',
            0  => 'C3',
            default => 'D',
        };
    }

    /**
     * Generate a simple ZRA item code: ZM2NTBA + zero-padded sequence.
     */
    private function itemCode(int $seq): string
    {
        return 'ZM2NTBA' . str_pad((string) $seq, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Make a POST request to the VSDC and return the decoded JSON body.
     *
     * @throws RuntimeException
     */
    private function post(Company $company, string $path, array $payload): array
    {
        $url = rtrim((string) $company->vsdc_url, '/') . $path;

        $response = Http::timeout(30)
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "VSDC request to {$path} failed with HTTP {$response->status()}: " . $response->body()
            );
        }

        $body = $response->json();

        if (($body['resultCd'] ?? '') !== '000') {
            throw new RuntimeException(
                "VSDC returned error [{$body['resultCd']}]: " . ($body['resultMsg'] ?? 'Unknown error')
            );
        }

        return $body;
    }

    private function assertConfigured(Company $company): void
    {
        if (! $company->vsdc_url || ! $company->vsdc_bhf_id) {
            throw new RuntimeException('VSDC is not configured for this company. Please set the VSDC URL and Branch ID in Company Settings.');
        }
    }
}
