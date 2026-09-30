export type Instrument = 'modis' | 'viirs';
export type HarmonizationMethod = 'ratio' | 'frp_threshold';
export type ReferenceSensor = 'modis' | 'viirs';
export type CalendarPeriod = 'day' | 'week' | 'month';
export type Severity = 'watch' | 'warning' | 'critical';

export type WindowStats = {
    cell_days: number;
    detections: number;
    frp_sum: number;
};

export type RegionSummary = {
    first_date: string | null;
    last_date: string | null;
    last_active_date: string | null;
    days_with_data: number;
    last_7_days: { modis: WindowStats; viirs: WindowStats };
    sparkline: (number | null)[];
    calibration: {
        calibrated: boolean;
        overlap_days: number | null;
        ratio: number | null;
        ratio_ci: [number | null, number | null] | null;
        frp_threshold_mw: number | null;
        mae_improvement: number | null;
    };
    alerts: number;
    top_alert: {
        period_start: string;
        severity: Severity;
        message: string;
        z_score: number;
    } | null;
};

export type Region = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    bbox: [number, number, number, number];
    center: { lat: number; lon: number };
    area_km2: number;
    color: string;
    is_preset: boolean;
    is_owner: boolean;
    harmonized_at: string | null;
    summary?: RegionSummary;
    links: { web: string; lab: string; api: string; calendar: string };
};

export type NativeStats = {
    detections: number;
    cell_days: number;
    active_cells: number;
    frp_sum: number;
    frp_max: number;
    footprint_km2: number;
    day_detections: number;
    night_detections: number;
    high_confidence: number;
    active_days: number;
    coverage_days: number;
};

export type SeriesPoint = {
    period_start: string;
    month: number;
    modis: NativeStats | null;
    viirs: NativeStats | null;
    harmonized: number | null;
    low: number | null;
    high: number | null;
    estimated: boolean;
    source: Instrument | null;
    factor: number | null;
    naive: number | null;
};

export type ErrorStats = {
    n: number;
    bias: number;
    mae: number;
    rmse: number;
    r2: number;
    mape: number | null;
};

export type Validation = {
    train_days: number;
    test_days: number;
    holdout: string;
    harmonized: ErrorStats;
    naive: ErrorStats;
    mae_improvement: number;
    correlation: number;
};

export type Series = {
    points: SeriesPoint[];
    factors: Record<string, { value: number; low: number; high: number }>;
    method: HarmonizationMethod;
    reference: ReferenceSensor;
    period: CalendarPeriod;
    calibrated: boolean;
    validation: Validation | null;
};

export type Factor = {
    method: HarmonizationMethod;
    method_label: string;
    reference: ReferenceSensor;
    month: number | null;
    metric: string;
    value: number;
    unit: string;
    ci95: [number | null, number | null];
    sample_days: number;
    overlap: [string | null, string | null];
    validation: Validation | null;
    version: string;
    calibrated_at: string;
};

export type FireAlert = {
    id: number;
    region?: { slug: string; name: string; color: string };
    period: CalendarPeriod;
    period_start: string;
    metric: string;
    method: HarmonizationMethod;
    reference: ReferenceSensor;
    observed: number;
    expected: number;
    spread: number;
    z_score: number;
    percentile: number;
    severity: Severity;
    climatology_years: number;
    message: string;
};

export type ClimatologyPoint = {
    index: number;
    label: string;
    typical: number | null;
    max: number | null;
    years: number;
};

export type CriticalPeriods = {
    period: CalendarPeriod;
    method: HarmonizationMethod;
    reference: ReferenceSensor;
    years: number;
    climatology: ClimatologyPoint[];
    peak: { index: number; label: string; typical: number } | null;
    season: {
        start: number;
        end: number;
        start_label: string;
        end_label: string;
        length: number;
        onset: number | null;
        onset_label: string | null;
    } | null;
    latest: {
        period_start: string;
        index: number;
        value: number;
        in_season: boolean;
    } | null;
};

export type GlobalSummary = {
    first_date: string | null;
    last_date: string | null;
    days: number;
    detections: number;
    cell_days: number;
    modis: { detections: number; cell_days: number; satellites: number };
    viirs: { detections: number; cell_days: number; satellites: number };
    regions: number;
    calibrated_regions: number;
    alerts: number;
};

export type OverlapPair = {
    date: string;
    month: number;
    modis: number;
    viirs: number;
};

export type ThresholdPoint = {
    tau: number;
    mae: number;
    bias: number;
    predicted: number;
    observed: number;
};

export type DetectionCurve = {
    days: number;
    bins: {
        bin: number;
        frp_from: number;
        frp_to: number | null;
        viirs: number;
        seen_by_modis: number;
        probability: number | null;
    }[];
    modis_only: number;
    viirs_only: number;
    both: number;
};

export type Receipt = {
    application: string;
    generated_at: string;
    region: { slug: string; name: string; bbox: number[]; area_km2: number };
    grid: {
        harmonization_cell_km: number;
        calendar_cell_deg: number;
        cell_day_definition: string;
    };
    period: string;
    method: {
        name: string;
        label: string;
        reference: string;
        version: string;
        factor: number | null;
        ci95: [number | null, number | null] | null;
        overlap: [string | null, string | null] | null;
        overlap_days: number | null;
        validation: Validation | null;
        calibrated_at: string | null;
    };
    sources: {
        modis: string;
        viirs: string;
        static_sources_excluded: boolean;
        ingest_runs: {
            source: string;
            instrument: string | null;
            from: string | null;
            to: string | null;
            rows: number;
        }[];
    };
    caveats: string[];
};

export type SelectOption<T extends string = string> = {
    value: T;
    label: string;
    description?: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
};

export type IngestStatus = 'running' | 'completed' | 'failed';

export type IntakeInstrumentRows = { modis: number; viirs: number };

export type IntakeRunCounts = {
    completed: number;
    failed: number;
    running: number;
};

export type IntakeBackfill = {
    id: string;
    total: number;
    done: number;
    failed: number;
    started_at: string;
    percent: number;
};

export type IntakeArea = {
    id: number;
    slug: string;
    name: string;
    color: string;
    area_km2: number;
    rows_read: number;
    rows_inserted: number;
    rows_skipped: number;
    by_instrument: IntakeInstrumentRows;
    runs: IntakeRunCounts;
    first_date: string | null;
    last_date: string | null;
    last_run_at: string | null;
    last_finished_at: string | null;
    last_error: string | null;
    harmonized_at: string | null;
    history: {
        windows_total: number;
        windows_done: number;
        percent: number;
        earliest_possible: string;
    };
    backfill: IntakeBackfill | null;
    links: { web: string };
};

export type IntakeRun = {
    id: number;
    source: string;
    label: string;
    instrument: Instrument | null;
    region: { slug: string; name: string; color: string } | null;
    status: IngestStatus;
    rows_read: number;
    rows_inserted: number;
    rows_skipped: number;
    date_from: string | null;
    date_to: string | null;
    started_at: string;
    finished_at: string | null;
    duration_s: number | null;
    error: string | null;
};

export type DataIntake = {
    generated_at: string;
    totals: {
        areas: number;
        rows_inserted: number;
        modis_rows: number;
        viirs_rows: number;
        active_backfills: number;
        running_runs: number;
        failed_runs: number;
        history_percent: number;
        last_activity_at: string | null;
    };
    public_feed: {
        rows_inserted: number;
        by_instrument: IntakeInstrumentRows;
        runs: IntakeRunCounts;
        last_run_at: string | null;
        next_run_at: string | null;
        window: string;
        latest: {
            feed: string;
            status: IngestStatus;
            rows_inserted: number;
            started_at: string;
        }[];
    };
    areas: IntakeArea[];
    recent_runs: IntakeRun[];
};

export type AlertMapRegion = {
    slug: string;
    name: string;
    color: string;
    is_preset: boolean;
    bbox: [number, number, number, number];
    center: { lat: number; lon: number };
    alerts: { total: number; critical: number; warning: number; watch: number };
    top: {
        period: CalendarPeriod;
        period_start: string;
        severity: Severity;
        z_score: number;
        message: string;
    } | null;
};
