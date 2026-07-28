<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\CreditTransaction;
use App\Models\GenerationHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UsageDashboardController extends Controller
{
    public function index(): InertiaResponse
    {
        $user = Auth::user();

        $stats = Cache::remember("usage_stats_{$user->id}", 300, function () use ($user) {
            $thirtyDaysAgo = now()->subDays(30);

            $dailyUsage = AiUsageLog::where('user_id', $user->id)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(created_at) as date, SUM(credits_used) as credits')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => ['date' => $row->date, 'credits' => (float) $row->credits])
                ->values()
                ->toArray();

            $topTools = AiUsageLog::where('user_id', $user->id)
                ->whereNotNull('tool_slug')
                ->selectRaw('tool_slug, COUNT(*) as count')
                ->groupBy('tool_slug')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['tool_slug' => $row->tool_slug, 'count' => $row->count]);

            $topToolNames = AiTool::whereIn('slug', $topTools->pluck('tool_slug'))->pluck('name', 'slug');
            $topTools = $topTools->map(fn ($t) => [
                'tool_slug' => $t['tool_slug'],
                'tool_name' => $topToolNames[$t['tool_slug']] ?? $t['tool_slug'],
                'count' => $t['count'],
            ])->values()->toArray();

            $totalCreditsUsed = AiUsageLog::where('user_id', $user->id)->sum('credits_used');

            $peakHour = AiUsageLog::where('user_id', $user->id)
                ->selectRaw(self::sqlHour('created_at').' as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderByDesc('count')
                ->first();

            $totalTokens = AiUsageLog::where('user_id', $user->id)
                ->sum(DB::raw('input_tokens + output_tokens'));

            $totalGenerations = AiUsageLog::where('user_id', $user->id)->count();

            // Grouped by weekday NUMBER and named in PHP: DAYNAME() is MySQL-only, and it also
            // returns an untranslatable English name straight from the database.
            $mostActiveWeekday = AiUsageLog::where('user_id', $user->id)
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->selectRaw(self::sqlWeekday('created_at').' as weekday, COUNT(*) as count')
                ->groupBy('weekday')
                ->orderByDesc('count')
                ->first();

            $recentHistory = GenerationHistory::where('user_id', $user->id)
                ->latest('created_at')
                ->limit(10)
                ->get(['ulid', 'tool_slug', 'output_preview', 'created_at']);

            // Friendly names for tool_slugs
            $historyToolNames = AiTool::whereIn('slug', $recentHistory->pluck('tool_slug')->unique()->filter())
                ->pluck('name', 'slug');
            $recentHistory = $recentHistory->map(fn ($h) => [
                'ulid' => $h->ulid,
                'tool_slug' => $h->tool_slug,
                'tool_name' => $h->tool_slug ? ($historyToolNames[$h->tool_slug] ?? $h->tool_slug) : 'Direct',
                'output_preview' => $h->output_preview,
                'created_at' => $h->created_at?->toISOString(),
            ])->values();

            return [
                'total_generations' => $totalGenerations,
                'total_tokens' => (int) $totalTokens,
                'total_credits_used' => (float) $totalCreditsUsed,
                'daily_usage' => $dailyUsage,
                'top_tools' => $topTools,
                'peak_hour' => $peakHour ? (int) $peakHour->hour : null,
                'most_active_day' => $mostActiveWeekday
                    ? self::weekdayName((int) $mostActiveWeekday->weekday)
                    : null,
                'avg_tokens_per_gen' => $totalGenerations > 0 ? round($totalTokens / $totalGenerations) : 0,
                'recent_history' => $recentHistory,
            ];
        });

        // Live balance fields — always fresh (never cached), so they reflect the
        // user's real credit state immediately after a generation rather than the
        // 5-minute-stale cached snapshot. The plan credit limit mirrors the app-wide
        // monthly-limit source used by TokenGuard (free_credits_monthly was never set).
        $planCreditLimit = $user->plan?->credits
            ?? $user->monthly_limit
            ?? (float) settings('user_monthly_credit_limit', 0);

        // The wallet holds two kinds of credit with different rules: the plan allowance,
        // which is refreshed to the plan figure each period, and separately purchased
        // top-ups, which are the user's own money and survive every renewal. Shown apart
        // so nobody has to guess which part of a balance resets — the aggregate is still
        // what the sidebar reports.
        $topupCredits = (float) $user->topup_credits;

        $stats = array_merge($stats, [
            'credits_remaining' => (float) $user->credits,
            'credits_used_today' => (float) $user->credits_used_today,
            'credits_used_month' => (float) $user->credits_used_month,
            'plan_credit_limit' => (float) $planCreditLimit,
            'topup_credits' => $topupCredits,
            // Whatever is left of the allowance, i.e. the part that will be topped back up.
            'plan_credits_remaining' => max(0, (float) $user->credits - $topupCredits),
        ]);

        // Deliberately OUTSIDE the 5-minute stats cache: it is paginated (the cache key does
        // not vary by page) and a wallet ledger that lags five minutes behind a purchase
        // reads as a bug. The dashboard's "Wallet activity" panel links here for the full
        // list, so this page has to actually carry it.
        $transactions = tap(
            $user->creditTransactions()
                ->latest()
                ->paginate(15, ['*'], 'wallet_page')
                ->withQueryString()
                ->fragment('wallet-activity'),
            function ($paginator) {
                $paginator->getCollection()->transform(fn (CreditTransaction $tx) => [
                    'id' => $tx->id,
                    'amount' => (float) $tx->amount,
                    'balance_after' => (float) $tx->balance_after,
                    'type' => $tx->type,
                    'description' => $tx->description,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ]);
            }
        );

        return Inertia::render('User/Usage', [
            'stats' => $stats,
            'transactions' => $transactions,
        ]);
    }

    public function export(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        $filename = 'my-ai-usage-'.now()->format('Y-m-d').'.xlsx';

        // Build friendly name lookup: slug → display name
        $toolNames = AiTool::pluck('name', 'slug')->toArray();

        $rows = AiUsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->orderBy('created_at', 'desc')
            ->get();

        // Model & provider are intentionally omitted — they are internal/admin
        // details and must not be exposed in the user-facing export.
        $headers = ['Date', 'Tool', 'Input Tokens', 'Output Tokens', 'Credits Used', 'Status'];

        // Extract template_slug from metadata for rows where tool_slug is null
        $resolveDisplaySlug = function ($row): ?string {
            if ($row->tool_slug) {
                return $row->tool_slug;
            }
            $meta = $row->metadata;
            if (is_string($meta)) {
                $meta = json_decode($meta, true);
            }
            return is_array($meta) ? ($meta['template_slug'] ?? null) : null;
        };

        // Friendly name resolver
        $resolveName = function (?string $slug) use ($toolNames): string {
            if (! $slug) {
                return 'Direct';
            }

            return $toolNames[$slug] ?? $slug;
        };

        // Build shared strings + data
        $stringIndex = [];
        $sharedStrings = [];

        $rowData = [];
        foreach ($rows as $r) {
            $displaySlug = $resolveDisplaySlug($r);
            $toolName = $resolveName($displaySlug);
            $vals = [
                $r->created_at->format('Y-m-d H:i'),
                $toolName,
                $r->input_tokens ?? 0,
                $r->output_tokens ?? 0,
                $r->credits_used ?? 0,
                $r->status ?? '-',
            ];
            $rowData[] = $vals;
            foreach ($vals as $v) {
                $v = (string) $v;
                if (! isset($stringIndex[$v])) {
                    $stringIndex[$v] = count($sharedStrings);
                    $sharedStrings[] = $v;
                }
            }
        }
        foreach ($headers as $h) {
            if (! isset($stringIndex[$h])) {
                $stringIndex[$h] = count($sharedStrings);
                $sharedStrings[] = $h;
            }
        }

        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        // OVERWRITE avoids the "empty file" deprecation from opening the fresh tempnam() file.
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'.
            '</Types>');

        // _rels/.rels
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>');

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'.
            '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'.
            '</Relationships>');

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets><sheet name="My AI Usage" sheetId="1" r:id="rId1"/></sheets>'.
            '</workbook>');

        // xl/styles.xml — two cell styles: [0] normal with border, [1] bold header with border
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'.
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'.
            '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border></borders>'.
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'.
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0"><alignment wrapText="1"/></xf></cellXfs>'.
            '</styleSheet>');

        // xl/sharedStrings.xml
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $s) {
            $ssXml .= '<si><t>'.$esc($s).'</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        // xl/worksheets/sheet1.xml
        $colWidths = [18, 20, 14, 14, 14, 12];
        $colCount = count($headers);
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>';
        for ($c = 0; $c < $colCount; $c++) {
            $sheetXml .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$colWidths[$c].'" customWidth="1"/>';
        }
        $sheetXml .= '</cols><sheetData>';

        // Header row (style index 1 = bold)
        $sheetXml .= '<row r="1">';
        foreach ($headers as $ci => $h) {
            $colRef = self::colLetter($ci).'1';
            $sheetXml .= '<c r="'.$colRef.'" s="1" t="s"><v>'.$stringIndex[$h].'</v></c>';
        }
        $sheetXml .= '</row>';

        // Data rows
        $rowNum = 2;
        foreach ($rowData as $vals) {
            $sheetXml .= '<row r="'.$rowNum.'">';
            for ($c = 0; $c < $colCount; $c++) {
                $val = $vals[$c];
                $colRef = self::colLetter($c).$rowNum;
                $key = (string) $val;
                if (isset($stringIndex[$key])) {
                    $sheetXml .= '<c r="'.$colRef.'" s="0" t="s"><v>'.$stringIndex[$key].'</v></c>';
                } else {
                    $sheetXml .= '<c r="'.$colRef.'" s="0"><v>'.number_format((float) $val, 0, '.', '').'</v></c>';
                }
            }
            $sheetXml .= '</row>';
            $rowNum++;
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function chart(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $period = $request->input('period', '1M');
        $data = $this->getChartData($user->id, $period);
        return response()->json($data);
    }

    private function getChartData(int $userId, string $period): array
    {
        switch ($period) {
            case '1D':
                $twentyFourHoursAgo = now()->subHours(24);
                $rows = AiUsageLog::where('user_id', $userId)
                    ->where('created_at', '>=', $twentyFourHoursAgo)
                    ->selectRaw('DATE(created_at) as label_date, '.self::sqlHour('created_at').' as label_hour, SUM(credits_used) as credits')
                    ->groupBy('label_date', 'label_hour')
                    ->orderBy('label_date', 'asc')
                    ->orderBy('label_hour', 'asc')
                    ->get();
                
                return $rows->map(fn ($row) => [
                    'label' => \Carbon\Carbon::parse($row->label_date)->setHour($row->label_hour)->translatedFormat('M j, g A'),
                    'value' => (float) $row->credits,
                    'is_current' => \Carbon\Carbon::parse($row->label_date)->setHour($row->label_hour)->isCurrentHour()
                ])->values()->toArray();

            case '7D':
                $sevenDaysAgo = now()->subDays(7);
                $rows = AiUsageLog::where('user_id', $userId)
                    ->where('created_at', '>=', $sevenDaysAgo)
                    ->selectRaw('DATE(created_at) as date, SUM(credits_used) as credits')
                    ->groupBy('date')
                    ->orderBy('date', 'asc')
                    ->get();
                
                return $rows->map(fn ($row) => [
                    'label' => \Carbon\Carbon::parse($row->date)->translatedFormat('M j'),
                    'value' => (float) $row->credits,
                    'is_current' => \Carbon\Carbon::parse($row->date)->isToday()
                ])->values()->toArray();

            case '1Y':
                $oneYearAgo = now()->subMonths(12)->startOfMonth();
                $rows = AiUsageLog::where('user_id', $userId)
                    ->where('created_at', '>=', $oneYearAgo)
                    ->selectRaw(self::sqlYear('created_at').' as year, '.self::sqlMonth('created_at').' as month, SUM(credits_used) as credits')
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
                
                return $rows->map(fn ($row) => [
                    'label' => \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('M Y'),
                    'value' => (float) $row->credits,
                    'is_current' => \Carbon\Carbon::create($row->year, $row->month, 1)->isCurrentMonth()
                ])->values()->toArray();

            case '1M':
            default:
                $thirtyDaysAgo = now()->subDays(30);
                $rows = AiUsageLog::where('user_id', $userId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->selectRaw('DATE(created_at) as date, SUM(credits_used) as credits')
                    ->groupBy('date')
                    ->orderBy('date', 'asc')
                    ->get();
                
                return $rows->map(fn ($row) => [
                    'label' => \Carbon\Carbon::parse($row->date)->translatedFormat('M j'),
                    'value' => (float) $row->credits,
                    'is_current' => \Carbon\Carbon::parse($row->date)->isToday()
                ])->values()->toArray();
        }
    }

    private static function colLetter(int $i): string
    {
        return chr(65 + $i);
    }

    /**
     * Date-part expressions that work on both MySQL and SQLite.
     *
     * HOUR()/YEAR()/MONTH()/DAYNAME() are MySQL-only, so this page threw a 500 on any SQLite
     * install (and could not be covered by a feature test at all, since the suite runs on
     * sqlite :memory:). SQLite's strftime returns a zero-padded string, hence the cast.
     */
    private static function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private static function sqlHour(string $column): string
    {
        return self::isSqlite()
            ? "CAST(strftime('%H', {$column}) AS INTEGER)"
            : "HOUR({$column})";
    }

    private static function sqlYear(string $column): string
    {
        return self::isSqlite()
            ? "CAST(strftime('%Y', {$column}) AS INTEGER)"
            : "YEAR({$column})";
    }

    private static function sqlMonth(string $column): string
    {
        return self::isSqlite()
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";
    }

    /**
     * Weekday as an integer, 0 = Sunday on both engines: SQLite's strftime('%w') is already
     * 0-based from Sunday, MySQL's DAYOFWEEK() is 1-based from Sunday.
     */
    private static function sqlWeekday(string $column): string
    {
        return self::isSqlite()
            ? "CAST(strftime('%w', {$column}) AS INTEGER)"
            : "(DAYOFWEEK({$column}) - 1)";
    }

    /**
     * Weekday number to a translatable name. Previously DAYNAME() returned an English name
     * straight from the database, which no locale could translate.
     */
    private static function weekdayName(int $weekday): string
    {
        return [
            translate('Sunday'), translate('Monday'), translate('Tuesday'), translate('Wednesday'),
            translate('Thursday'), translate('Friday'), translate('Saturday'),
        ][$weekday] ?? '';
    }
}
