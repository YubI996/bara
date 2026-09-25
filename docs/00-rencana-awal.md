# Rencana Implementasi Platform Sistem Informasi Terintegrasi dan Pentahelix

## 1. Tujuan

Membangun **platform metadata-driven** yang memungkinkan banyak sistem informasi dibuat dan dijalankan di dalam satu platform, dengan:

- identitas bersama,
- master data bersama,
- shared data layer,
- entity dan relationship yang dapat digunakan ulang,
- form dan CRUD generator,
- workflow,
- processing engine,
- indikator,
- dashboard,
- event,
- API,
- data product,
- dan kolaborasi pentahelix.

Prinsip utama:

> **Aplikasi adalah komposisi yang dapat berubah. Data, identitas, relasi, proses, ownership, dan semantik adalah aset platform yang harus dapat digunakan ulang.**

---

## 2. Target Arsitektur

```text
                         PLATFORM
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
   App Builder         Shared Data         Platform Services
        │                   │                   │
   Entity Builder       Master Data          Identity
   Form Builder         Reference Data       Permission
   View Builder         Operational Data     Workflow
   Process Builder      Derived Data         Audit
        │                   │                 Events
        └───────────────────┼───────────────────┘
                            │
                    Processing Engine
                            │
          ┌─────────────────┼─────────────────┐
          │                 │                 │
       Logic            Statistics        Aggregation
          │                 │                 │
          └─────────────────┼─────────────────┘
                            │
                     Information Layer
                            │
             Dashboard / API / Report
                            │
              Pentahelix Collaboration
```

---

## 3. Tahapan Implementasi

| Tahap | Fokus | Hasil |
|---|---|---|
| 0 | Fondasi arsitektur | Terminologi, domain, data model |
| 1 | Metadata engine | Entity, field, relationship |
| 2 | Form & CRUD runtime | Sistem informasi sederhana dapat dibuat |
| 3 | Shared data layer | Data lintas aplikasi mulai terintegrasi |
| 4 | Processing engine | Data dapat diolah menjadi informasi |
| 5 | Indicator layer | Indikator reusable |
| 6 | Presentation engine | Tabel, KPI, chart, dashboard |
| 7 | Workflow engine | Submit, verify, approve, reject |
| 8 | Event engine | Otomasi lintas modul |
| 9 | Pentahelix layer | Aktor, isu, kontribusi, outcome |
| 10 | Data product & API | Informasi reusable lintas aplikasi |
| 11 | Advanced analytics | Statistik, GIS, forecasting |

---

## 4. Tahap 0 — Fondasi Arsitektur

Sebelum membuat migration atau UI, definisikan vocabulary platform.

```text
Application
Entity
Field
Relationship
Record

Form
Process
View
Action

Organization
Actor
Role
Permission

Dataset
Indicator
Issue
Program
Activity
Outcome
Contribution
```

Pisahkan secara konseptual:

```text
Platform Metadata
        ≠
Application Data
        ≠
Master Data
        ≠
Derived Information
```

Setiap entity utama sebaiknya memiliki konsep:

```text
id
code
name
owner
scope
visibility
created_at
updated_at
```

Tidak semua field harus terlihat oleh pengguna.

---

## 5. Tahap 1 — Metadata Engine

Metadata engine adalah inti platform.

Administrator dapat mendefinisikan:

```text
Entity: Dataset

Fields:
- title        string
- year         integer
- opd          relationship
- description  text
- status       enum
```

Tanpa membuat controller, model, dan form baru secara manual.

Struktur awal:

```text
applications
entities
fields
relationships
forms
views
processes
actions
```

Contoh:

```text
applications
------------
id
name
slug

entities
--------
id
application_id
name
slug
storage_type

fields
------
id
entity_id
name
type
required
config_json
```

Contoh konfigurasi field:

```json
{
  "min": 0,
  "max": 100,
  "default": 0
}
```

Fokus awal adalah fungsi, bukan drag-and-drop visual.

---

## 6. Tahap 2 — Runtime CRUD dan Form Engine

Metadata harus diterjemahkan menjadi aplikasi yang dapat dipakai.

Contoh definisi:

```text
Entity:
Employee

Fields:
- name
- nip
- opd
```

Platform dapat menghasilkan fungsi:

```text
/employees
/employees/create
/employees/{id}
/employees/{id}/edit
```

Alurnya:

```text
Entity Metadata
      ↓
Form Renderer
      ↓
Validation Engine
      ↓
Persistence
```

Field type minimum:

| Type | UI |
|---|---|
| string | text input |
| text | textarea |
| integer | number |
| decimal | number |
| boolean | checkbox / switch |
| date | date picker |
| datetime | datetime picker |
| enum | select |
| relationship | entity selector |
| file | upload |

Pada milestone ini, platform sudah dapat membuat sistem informasi CRUD sederhana.

---

## 7. Tahap 3 — Shared Data Layer

Bangun entity bersama untuk data yang digunakan lintas aplikasi.

Contoh:

```text
Core.OPD
Core.Person
Core.Employee
Core.Region
Core.FiscalYear
```

Hindari:

```text
app_a_opd
app_b_opd
app_c_opd
```

Gunakan:

```text
Application A ──┐
Application B ──┼──→ Core.OPD
Application C ──┘
```

Relationship cukup menyatakan:

```text
dataset.opd
    references
core.opd
```

---

## 8. Ownership Data

Setiap shared entity harus memiliki owner.

Contoh:

```text
Entity: OPD

owner:
organization_administration

consumers:
spbe
open_data
budgeting
asset_management
```

Aplikasi konsumen dapat membaca, mereferensikan, menggunakan, dan mengagregasi, tetapi tidak bebas mengubah master data.

---

## 9. Tahap 4 — Processing Engine

Processing engine mengubah data menjadi informasi.

```text
INPUT
  ↓
Validation
  ↓
Normalization
  ↓
Filter
  ↓
Join
  ↓
Group
  ↓
Aggregate
  ↓
Calculate
  ↓
Condition
  ↓
Classification
  ↓
Statistical Analysis
  ↓
Indicator
  ↓
OUTPUT
```

Struktur:

```text
processes
process_steps
process_inputs
process_outputs
```

Step awal:

```text
filter
select
join
group
count
sum
average
min
max
formula
condition
classification
```

Contoh:

```text
Source: Dataset
       ↓
Filter year = 2026
       ↓
Group by OPD
       ↓
Count all
       ↓
Count status = published
       ↓
Formula
published / total * 100
       ↓
Output:
publication_rate
```

Jangan mulai dengan visual node editor. Bangun execution engine terlebih dahulu.

---

## 10. Tahap 5 — Indicator Layer

Indicator harus menjadi first-class object.

```text
Indicator:
Persentase Dataset Dipublikasikan

source:
Dataset

formula:
published / registered × 100

dimension:
OPD

period:
year

unit:
%

owner:
Diskominfo
```

Alur:

```text
Raw Records
     ↓
Processing
     ↓
Indicator
     ↓
Dashboard / API / Report
```

Satu indikator harus dapat digunakan oleh banyak aplikasi dan dashboard.

---

## 11. Tahap 6 — Presentation Engine

Mulai dari komponen minimum:

| View | Fungsi |
|---|---|
| Table | record dan detail |
| KPI | satu indikator utama |
| Bar | perbandingan kategori |
| Line | tren |
| Pie / stacked | komposisi sederhana |
| Dashboard | komposisi view |

Dashboard tidak menyimpan business logic atau SQL sendiri. Dashboard hanya mengonsumsi indicator, reusable query, atau data product.

---

## 12. Tahap 7 — Workflow Engine

Tambahkan state machine untuk proses bisnis.

```text
Draft
  ↓
Submitted
  ↓
Verified
  ↓
Approved
  ↓
Published
```

Struktur:

```text
workflow_definitions
workflow_states
workflow_transitions
workflow_instances
workflow_history
```

Action:

```text
Submit
Verify
Reject
Return
Approve
Publish
```

Semua action harus tunduk pada permission engine.

---

## 13. Tahap 8 — Event Engine

Setelah workflow stabil, tambahkan event asynchronous.

```text
dataset.approved
       │
       ├── publish metadata
       ├── recalculate indicator
       ├── notify operator
       └── update dashboard cache
```

Laravel Events + Queue cukup untuk tahap awal.

```text
Domain Event
     ↓
Queue
     ↓
Listeners
```

Contoh event:

```text
record.created
record.updated
form.submitted
record.verified
record.approved
dataset.published
indicator.updated
```

---

## 14. Tahap 9 — Pentahelix Layer

Jangan membuat lima sistem yang benar-benar terpisah.

```text
Organization
    │
    ├── Government
    ├── Academia
    ├── Business
    ├── Community
    └── Media
```

Tambahkan entity:

```text
Issue
Program
Activity
Contribution
Partnership
Evidence
Outcome
```

Contoh:

```text
Issue:
"Pengurangan Sampah Plastik"
        │
        ├── Government
        │     └── regulation
        ├── University
        │     └── research
        ├── Business
        │     └── recycling facility
        ├── Community
        │     └── collection program
        └── Media
              └── public campaign
```

Pusat kolaborasi adalah shared context, bukan sekadar shared login.

---

## 15. Model Data Pentahelix

```text
Organization
     │
     ├── participates_in → Program
     ├── contributes_to → Activity
     ├── owns → Dataset
     ├── researches → Issue
     └── supports → Outcome
```

Platform kemudian dapat menelusuri:

```text
Issue X
  ↓
Programs
  ↓
Organizations
  ↓
Activities
  ↓
Datasets
  ↓
Indicators
  ↓
Outcomes
```

---

## 16. Tahap 10 — Data Product dan API

Informasi reusable dipublikasikan sebagai data product.

```text
Data Product:
population_by_kelurahan

Provider:
Population System

Consumers:
Health
Education
Planning
Social Assistance
Open Data
```

API:

```text
GET /api/data-products/population-by-kelurahan
```

Setiap data product minimal mempunyai:

```text
owner
schema
description
version
update_frequency
source
access_policy
quality_status
```

---

## 17. Struktur Backend

Rekomendasi modular monolith:

```text
app/
├── Domain/
│   ├── Metadata/
│   ├── Data/
│   ├── Identity/
│   ├── Workflow/
│   ├── Processing/
│   ├── Indicator/
│   ├── Integration/
│   ├── Organization/
│   └── Collaboration/
│
├── Application/
│   ├── Commands/
│   ├── Queries/
│   └── Services/
│
└── Infrastructure/
    ├── Persistence/
    ├── Queue/
    ├── Cache/
    └── External/
```

---

## 18. Stack Awal

```text
Backend      : Laravel
Frontend     : Vue
Database     : PostgreSQL
Metadata     : PostgreSQL JSONB
Queue/Cache  : Redis
Files        : S3-compatible object storage
GIS          : PostGIS
API          : REST
Auth         : Platform SSO / Laravel Auth
Authorization: RBAC + resource policies
```

Mulai sebagai modular monolith:

```text
One Deployment
One Database Cluster
Multiple Bounded Modules
```

---

## 19. Roadmap Milestone

| Milestone | Kemampuan |
|---|---|
| M1 | Admin membuat entity dan field |
| M2 | Entity menjadi form + CRUD |
| M3 | Relationship antar-entity |
| M4 | Shared master data |
| M5 | Role dan permission |
| M6 | Processing sederhana |
| M7 | Indicator |
| M8 | Table, KPI, chart |
| M9 | Workflow |
| M10 | Event + notification |
| M11 | Cross-application query |
| M12 | Pentahelix collaboration |
| M13 | Data product |
| M14 | GIS / statistical processing |

---

## 20. Evolusi Produk

Sampai M4:

> **Low-code CRUD Builder**

M6–M8:

> **Information System Platform**

M9–M11:

> **Integrated Information Platform**

M12+:

> **Pentahelix Collaboration and Shared Data Platform**

---

## 21. MVP Pertama

Gunakan satu use case nyata.

```text
Aplikasi:
Monitoring Program

Shared Entities:
OPD
Organization
Region

Application Entities:
Program
Activity
Indicator
Realization
```

Flow:

```text
Program
   ↓
Activity
   ↓
Realization
   ↓
Aggregate
   ↓
Indicator
   ↓
Dashboard
```

---

## 22. MVP Kedua

```text
Aplikasi:
Research / Collaboration

Entities:
Organization
Research
Issue
Dataset
Program
```

Hubungkan kedua aplikasi melalui:

```text
Organization
Issue
Dataset
Program
```

---

## 23. Uji Arsitektur Terpenting

Arsitektur dinilai berhasil jika aplikasi kedua dapat:

- menggunakan shared entity,
- menggunakan indikator yang sudah ada,
- membaca data product,
- mereferensikan master data,
- mendengarkan event,

tanpa:

- copy-paste data,
- membuat tabel master baru,
- membuat integrasi point-to-point khusus,
- mendefinisikan ulang konsep yang sama.

---

## 24. Prinsip Implementasi

### Shared First
Gunakan shared entity bila konsep dan maknanya sama lintas aplikasi.

### Ownership Explicit
Setiap data penting memiliki owner.

### Reference, Do Not Copy
Aplikasi mereferensikan master data, bukan membuat copy.

### Metadata over Hardcoding
Sistem baru sedapat mungkin dibuat melalui metadata.

### Raw Data ≠ Derived Information
Hasil pengolahan tidak menggantikan data sumber.

### Reusable Processing
Formula, indikator, dan pipeline harus dapat digunakan ulang.

### Event over Tight Coupling
Gunakan event untuk integrasi asynchronous.

### API by Default
Entity/data product penting tersedia melalui API sesuai permission.

### Audit Important Actions
Catat create, update, delete, submit, verify, reject, approve, publish, dan perubahan permission.

### Semantics Matter
Kesamaan nama belum berarti kesamaan konsep.

---

## 25. Anti-Pattern

Hindari:

- master data per aplikasi,
- semua modul mengakses semua tabel,
- business logic di dashboard,
- derived data menimpa source,
- terlalu cepat menggunakan microservices,
- terlalu cepat membuat visual builder,
- point-to-point integration berlebihan.

---

## 26. Definisi Keberhasilan Platform

Jangan mengukur keberhasilan hanya dari:

> Berapa banyak aplikasi yang dapat dibuat?

Ukuran yang lebih tepat:

> Seberapa sedikit konsep, data, relasi, formula, dan proses yang harus didefinisikan ulang saat membuat aplikasi baru?

Jika OPD, Region, Organization, Dataset, Program, dan Indicator harus dibuat ulang setiap kali ada aplikasi baru, maka produk masih berupa kumpulan aplikasi.

Jika aplikasi baru cukup:

```text
reference existing entity
reuse existing process
consume existing indicator
subscribe to existing event
consume existing data product
```

maka produk sudah benar-benar menjadi platform.

---

## 27. Arah Akhir

```text
Entity
   +
Relationship
   +
Input
   +
Process
   +
Indicator
   +
View
   +
Action
   +
Permission
   +
Event
   =
Information System
```

Dengan seluruh sistem informasi tetap berada di atas:

```text
Shared Identity
Shared Master Data
Shared Semantics
Shared Processing
Shared Data Products
Shared Integration Layer
```

Target akhir:

> **Low-code Application Platform + Shared Data Platform + Workflow Engine + Processing Engine + BI Platform + Integration Platform + Pentahelix Collaboration Layer**
