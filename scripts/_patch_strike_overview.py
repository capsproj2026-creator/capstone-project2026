from pathlib import Path

controller = Path(r"c:\Users\LIZZIE\Downloads\capstone-project2026-main\capstone-project2026-main\app\Http\Controllers\Admin\ViolationController.php")
blade = Path(r"c:\Users\LIZZIE\Downloads\capstone-project2026-main\capstone-project2026-main\resources\views\admin\violations.blade.php")

lines = controller.read_text(encoding="utf-8").splitlines(keepends=True)
nl = "\r\n" if lines and lines[0].endswith("\r\n") else "\n"

start = next(i for i, line in enumerate(lines) if "$strikeOverview = User::query()" in line)
end = start
while end < len(lines) and "->get([" not in lines[end]:
    end += 1
end += 1

new_block = [
    "        $strikeOverviewQuery = User::query()" + nl,
    "            ->whereIn('user_role_id', [3, 4])" + nl,
    "            ->where('strike_count', '>=', 1);" + nl,
    nl,
    "        $strikeOverviewCount = (clone $strikeOverviewQuery)->count();" + nl,
    nl,
    "        $strikeOverview = (clone $strikeOverviewQuery)" + nl,
    "            ->orderByDesc('strike_count')" + nl,
    "            ->orderBy('name')" + nl,
    "            ->limit(12)" + nl,
    "            ->get(['id', 'name', 'strike_count', 'status', 'plate_number', 'id_number']);" + nl,
]

text = "".join(lines[:start] + new_block + lines[end:])
needle = f"            'strikeOverview' => $strikeOverview,{nl}            'typeCounts' => $typeCounts,"
repl = (
    f"            'strikeOverview' => $strikeOverview,{nl}"
    f"            'strikeOverviewCount' => $strikeOverviewCount,{nl}"
    f"            'typeCounts' => $typeCounts,"
)
if needle not in text:
    raise SystemExit("return keys not found")
text = text.replace(needle, repl, 1)
controller.write_text(text, encoding="utf-8")
print("controller ok")

bt = blade.read_text(encoding="utf-8")
marker = '3-Strike System Overview</h3>'
pos = bt.find(marker)
if pos < 0:
    raise SystemExit("marker missing")
div_start = bt.rfind('<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">', 0, pos)
next_panel = bt.find('Violation Types</h3>', pos)
div_next = bt.rfind('<div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">', div_start + 1, next_panel)
if div_start < 0 or div_next < 0:
    raise SystemExit(f"div bounds missing {div_start} {div_next}")

replacement = """            <div class=\"rounded-xl border border-gray-200 bg-white p-5 shadow-sm\">
                <div class=\"flex items-start justify-between gap-3\">
                    <div class=\"min-w-0\">
                        <h3 class=\"text-base font-semibold text-gray-900\">3-Strike System Overview</h3>
                        <p class=\"mt-0.5 text-xs text-gray-500\">
                            {{ number_format($strikeOverviewCount ?? $strikeOverview->count()) }}
                            {{ ($strikeOverviewCount ?? $strikeOverview->count()) === 1 ? 'user' : 'users' }}
                            with violations
                        </p>
                    </div>
                    @if (($strikeOverviewCount ?? 0) > 0)
                        <span class=\"inline-flex shrink-0 items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800\">
                            {{ number_format($strikeOverviewCount) }}
                        </span>
                    @endif
                </div>
                <div class=\"mt-4 max-h-[24rem] space-y-4 overflow-y-auto\">
                    @forelse ($strikeOverview as $user)
                        @php
                            $strikes = min(3, (int) ($user->strike_count ?? 0));
                            $locked = $user->isLocked() || $strikes >= 3;
                            $barHex = $strikes >= 3 ? '#ef4444' : ($strikes === 2 ? '#f97316' : ($strikes === 1 ? '#fbbf24' : null));
                            $badgeClass = $strikes >= 3
                                ? 'bg-red-100 text-red-700'
                                : ($strikes === 2 ? 'bg-orange-100 text-orange-700' : 'bg-amber-100 text-amber-800');
                            $sanctionName = \\App\\Support\\ViolationSanctionPresenter::nameForStrike($strikes);
                        @endphp
                        <a href=\"{{ route('admin.violations', ['q' => $user->name]) }}\" class=\"block rounded-lg p-1 transition hover:bg-gray-50\">
                            <div class=\"mb-2 flex items-center justify-between gap-2\">
                                <p class=\"truncate text-sm font-semibold text-gray-900\">{{ $user->name }}</p>
                                <span class=\"shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badgeClass }}\">
                                    {{ $strikes }}/3 Strikes
                                </span>
                            </div>
                            @if ($sanctionName)
                                <p class=\"mb-2 text-[11px] font-medium leading-relaxed text-gray-500\">{{ $sanctionName }}</p>
                            @endif
                            <div class=\"flex gap-1\">
                                @for ($i = 1; $i <= 3; $i++)
                                    <div
                                        class=\"h-2.5 flex-1 rounded-full {{ $i > $strikes ? 'bg-gray-200' : '' }}\"
                                        @if ($i <= $strikes && $barHex)
                                            style=\"background-color: {{ $barHex }};\"
                                        @endif
                                    ></div>
                                @endfor
                            </div>
                            @if ($locked)
                                <p class=\"mt-2 flex items-center gap-1 text-xs font-medium text-red-600\">
                                    <i data-lucide=\"lock\" class=\"h-3 w-3\"></i>
                                    Account Suspended
                                </p>
                            @elseif ($strikes === 2)
                                <p class=\"mt-2 flex items-center gap-1 text-xs font-medium text-orange-600\">
                                    <i data-lucide=\"alert-triangle\" class=\"h-3 w-3\"></i>
                                    One more violation will suspend account
                                </p>
                            @endif
                        </a>
                    @empty
                        <p class=\"text-sm text-gray-500\">No users with violations yet.</p>
                    @endforelse
                </div>
            </div>

"""
replacement = replacement.replace("\\\\App\\\\Support\\\\", "\\App\\Support\\")
if nl == "\r\n":
    replacement = replacement.replace("\n", "\r\n")

bt = bt[:div_start] + replacement + bt[div_next:]
blade.write_text(bt, encoding="utf-8")
print("blade ok")
