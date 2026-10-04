<style>
    @import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap');

    @page {
        size: A4;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', 'Ubuntu', sans-serif;
        font-size: 13px;
        color: #1f2937;
        line-height: 1.5;
        margin: 7.5mm;
        padding: 0;
    }

    /* Icon style */
    .section-icon {
        display: none;
    }

    /* Intestazione */
    .header {
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .header .title h1 {
        font-size: 24px;
        color: #000;
        margin: 0 0 12px 0;
        font-weight: bold;
        text-align: center;
    }

    .header .subtitle {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 6px;
    }

    .header .badge {
        background: #0d6efd;
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }

    .header .timestamp {
        text-align: right;
        font-size: 11px;
        color: #6b7280;
        margin: 0;
    }

    /* Hero */
    .hero {
        text-align: center;
        padding: 15px;
        background: #f0f5ff;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .hero .code {
        font-size: 32px;
        font-weight: bold;
        color: #0d6efd;
        margin: 0;
    }

    .hero .plate {
        font-size: 15px;
        color: #374151;
        margin-top: 4px;
        font-weight: 500;
    }

    /* Sezioni */
    .section-title {
        display: flex;
        align-items: center;
        line-height: 1.2;
        font-size: 13px;
        font-weight: bold;
        color: white;
        background: #0d6efd;
        padding: 8px 12px;
        margin-top: 15px;
        margin-bottom: 10px;
        border-radius: 4px;
    }

    /* Info card */
    .info-card {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 10px;
        page-break-inside: avoid;
    }

    .info-card h3 {
        font-size: 13px;
        color: #0d6efd;
        margin-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 4px;
        font-weight: bold;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 12px;
        align-items: center;
    }

    .info-row .label {
        color: #6b7280;
        font-weight: 500;
    }

    .info-row .value {
        font-weight: 600;
        color: #1f2937;
        text-align: right;
    }

    /* Tabelle */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
        font-size: 12px;
    }

    th {
        background: #f3f4f6;
        border-bottom: 2px solid #d1d5db;
        padding: 6px 8px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        font-size: 12px;
        font-family: 'DejaVu Sans', 'Ubuntu', sans-serif;
    }

    td {
        padding: 5px 8px;
        border-bottom: 1px solid #e5e7eb;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tr:nth-child(even) {
        background: #f9fafb;
    }

    /* Tag stati */
    .tag {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    .tag-red {
        background: #fee2e2;
        color: #991b1b;
    }

    .tag-green {
        background: #dcfce7;
        color: #166534;
    }

    .tag-yellow {
        background: #fef3c7;
        color: #b45309;
    }

    .tag-blue {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Messaggio vuoto */
    .empty {
        color: #9ca3af;
        font-style: italic;
        padding: 10px 0;
        font-size: 12px;
    }

    /* Footer */
    .footer {
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
        border-top: 1px solid #e5e7eb;
        padding-top: 10px;
        margin-top: 15px;
    }

    /* Page break control */
    .section {
        page-break-inside: avoid;
    }

    .info-grid {
        display: flex;
        gap: 22px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .info-grid .info-card {
        flex: 1;
        min-width: 48%;
        margin-bottom: 8px;
    }

    .page-break {
        page-break-before: always;
    }
</style>
