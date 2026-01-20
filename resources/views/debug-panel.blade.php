<style>
#qd-panel {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 400px;
    background: #1a1a2e;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    z-index: 999999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: #fff;
    overflow: hidden;
    transition: all 0.3s ease;
}
#qd-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 12px 16px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
}
#qd-content {
    max-height: 500px;
    overflow-y: auto;
    padding: 16px;
    background: #16213e;
}
#qd-content.collapsed {
    display: none;
}
.qd-stat {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    margin: 8px 0;
    background: rgba(255,255,255,0.05);
    border-radius: 6px;
    font-size: 14px;
}
.qd-stat-label {
    opacity: 0.7;
}
.qd-stat-value {
    font-weight: bold;
    color: #4ade80;
}
.qd-stat-value.warning {
    color: #fbbf24;
}
.qd-stat-value.error {
    color: #ef4444;
}
.qd-issue {
    background: rgba(239, 68, 68, 0.1);
    border-left: 3px solid #ef4444;
    padding: 12px;
    margin: 12px 0;
    border-radius: 4px;
}
.qd-code {
    background: rgba(0,0,0,0.4);
    padding: 12px;
    border-radius: 4px;
    font-family: 'Monaco', 'Courier New', monospace;
    font-size: 11px;
    margin: 8px 0;
    overflow-x: auto;
    line-height: 1.5;
}
.qd-badge {
    background: #ef4444;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
.qd-badge.success {
    background: #10b981;
}
.qd-toggle {
    font-size: 18px;
    transition: transform 0.3s;
}
.qd-toggle.collapsed {
    transform: rotate(-90deg);
}
.qd-label {
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.6;
    margin-bottom: 4px;
    font-weight: 600;
}
</style>

<div id="qd-panel">
    <div id="qd-header" onclick="toggleQD()">
        <div>
            <strong>⚡ Query Debugger</strong>
            @if(count($n1Issues) > 0)
                <span class="qd-badge">{{ count($n1Issues) }} N+1</span>
            @else
                <span class="qd-badge success">✓ OK</span>
            @endif
        </div>
        <span class="qd-toggle collapsed" id="qd-toggle">▼</span>
    </div>

    <div id="qd-content" class="collapsed">
        <!-- Statistics -->
        <div class="qd-stat">
            <span class="qd-stat-label">Total Queries</span>
            <span class="qd-stat-value {{ $stats['total_queries'] > 10 ? 'warning' : '' }}">
                {{ $stats['total_queries'] }}
            </span>
        </div>

        <div class="qd-stat">
            <span class="qd-stat-label">Total Time</span>
            <span class="qd-stat-value {{ $stats['total_time'] > 100 ? 'warning' : '' }}">
                {{ number_format($stats['total_time'], 2) }}ms
            </span>
        </div>

        @if($stats['slow_queries'] > 0)
        <div class="qd-stat">
            <span class="qd-stat-label">Slow Queries</span>
            <span class="qd-stat-value error">{{ $stats['slow_queries'] }}</span>
        </div>
        @endif

        <!-- N+1 Issues -->
        @if(count($n1Issues) > 0)
            @foreach($n1Issues as $index => $issue)
            <div class="qd-issue">
                <div style="font-weight: bold; margin-bottom: 8px;">
                    🚨 N+1 Issue #{{ $index + 1 }}
                </div>

                <div style="font-size: 12px; opacity: 0.8; margin-bottom: 8px;">
                    <strong>Count:</strong> {{ $issue['count'] }} repeated queries
                </div>

                @if(isset($issue['table']))
                <div style="font-size: 12px; opacity: 0.8; margin-bottom: 8px;">
                    <strong>Table:</strong> {{ $issue['table'] }}
                </div>
                @endif

                @if(isset($suggestions[$index]))
                    @php $suggestion = $suggestions[$index]; @endphp

                    @if(isset($suggestion['suggestion']))
                    <div style="margin: 12px 0;">
                        <div class="qd-label">💡 Suggestion</div>
                        <div style="font-size: 13px;">{{ $suggestion['suggestion'] }}</div>
                    </div>
                    @endif

                    @if(isset($suggestion['code_example']))
                    <div style="margin: 12px 0;">
                        <div class="qd-label">❌ Before</div>
                        <div class="qd-code">{{ $suggestion['code_example']['before'] ?? 'N/A' }}</div>
                    </div>

                    <div style="margin: 12px 0;">
                        <div class="qd-label">✅ After</div>
                        <div class="qd-code">{{ $suggestion['code_example']['after'] ?? 'N/A' }}</div>
                    </div>
                    @endif

                    @if(isset($suggestion['impact']['percent_reduction']))
                    <div style="margin: 12px 0; padding: 8px; background: rgba(16, 185, 129, 0.1); border-radius: 4px;">
                        <strong style="color: #10b981;">📊 Impact:</strong>
                        <span style="color: #10b981;">{{ $suggestion['impact']['percent_reduction'] }} fewer queries</span>
                    </div>
                    @endif
                @endif
            </div>
            @endforeach
        @else
            <div style="text-align: center; padding: 20px; opacity: 0.6;">
                <div style="font-size: 40px; margin-bottom: 8px;">✓</div>
                <div>No N+1 issues detected!</div>
            </div>
        @endif

        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 11px; opacity: 0.6; text-align: center;">
            Development mode only
        </div>
    </div>
</div>

<script>
function toggleQD() {
    const content = document.getElementById('qd-content');
    const toggle = document.getElementById('qd-toggle');
    content.classList.toggle('collapsed');
    toggle.classList.toggle('collapsed');
}
</script>
