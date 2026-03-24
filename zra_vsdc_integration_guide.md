# ZRA VSDC Integration Guide (Production-Ready)

This document outlines how to implement a robust, scalable, and compliant ZRA Smart Invoice integration in your SaaS platform.

It covers three critical areas:

1. VSDC Lifecycle Management
2. ZRA Compliance Validation
3. VSDC Monitoring Dashboard

---

# 1. VSDC Lifecycle Management

## Overview

Each company must have its own VSDC instance and go through a lifecycle:

Setup → Initialize → Use → Monitor → Recover (if needed)

---

## 1.1 Database Structure

### Companies Table (existing)
Add/ensure fields:

vsdc_url  
vsdc_bhf_id  
vsdc_dvc_srl_no  
vsdc_initialized  
vsdc_sdc_id  
vsdc_mrc_no  
vsdc_environment  
vsdc_last_seen_at  
vsdc_status  

---

## 1.2 Initialization Flow

### Step 1: Admin assigns VSDC
Company → vsdc_url = http://vsdc-client-x:8080

### Step 2: Initialize Device
POST /initializer/selectInitInfo

Payload:
{
  "tpin": "1000000000",
  "bhfId": "000",
  "dvcSrlNo": "DEVICE123"
}

### Step 3: Store Response

$company->update([
    'vsdc_initialized' => true,
    'vsdc_sdc_id' => $data['sdcId'],
    'vsdc_mrc_no' => $data['mrcNo'],
]);

---

## 1.3 Readiness Check

public function isVsdcReady(): bool
{
    return $this->vsdc_initialized
        && $this->vsdc_url
        && $this->vsdc_bhf_id
        && $this->vsdc_sdc_id
        && $this->vsdc_mrc_no;
}

---

## 1.4 Usage (Sales & Purchases)

if (!$company->isVsdcReady()) {
    throw new RuntimeException('VSDC not ready.');
}

---

## 1.5 Retry & Queue System

dispatch(new SubmitZraSaleJob($invoice));

class SubmitZraSaleJob implements ShouldQueue
{
    public $tries = 5;
    public $backoff = [60, 120, 300];

    public function handle(ZraVsdcService $service)
    {
        $service->submitSale($this->invoice);
    }
}

---

## 1.6 Error Logging

logger()->error('ZRA Error', [
    'company_id' => $company->id,
    'payload' => $payload,
    'response' => $response
]);

---

# 2. ZRA Compliance Validation

## Required Fields per Item

- Description  
- Quantity  
- Unit Price  
- Tax Rate  
- itemClsCd  

---

## Validation Layer

foreach ($invoice->items as $item) {
    if (!$item->itemClsCd()) {
        throw new RuntimeException("Missing ZRA code");
    }
}

---

## Remove Fallback Codes

Do NOT use fallback itemClsCd.

---

## Tax Mapping

16% → A  
0% → C3  
null → D  

---

## Idempotency

Use unique invoice numbers and prevent duplicate submissions.

---

# 3. VSDC Monitoring Dashboard

## Database

vsdc_status  
vsdc_last_seen_at  

vsdc_logs table:
- company_id  
- action  
- payload  
- response  
- status  

---

## Health Check

foreach ($companies as $company) {
    try {
        Http::get($company->vsdc_url);
        $company->update(['vsdc_status' => 'online']);
    } catch (Exception $e) {
        $company->update(['vsdc_status' => 'offline']);
    }
}

---

## Dashboard UI

- Status  
- Last Seen  
- Errors  
- Retry button  

---

# Final Architecture

User → Invoice → Queue → VSDC → ZRA

---

# Summary

You now have:
- Lifecycle management  
- Compliance validation  
- Monitoring system  

---

Production-ready ZRA integration.
