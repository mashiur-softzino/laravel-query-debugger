<div id="query-debugger-banner" style="position: fixed; bottom: 0; left: 0; right: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; box-shadow: 0 -4px 20px rgba(0,0,0,0.3); z-index: 99999; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-height: 60vh; overflow-y: auto;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
            <div>
                <h3 style="margin: 0 0 5px 0; font-size: 20px; font-weight: 600;">
                    ⚠️ Query Performance Warning
                </h3>
                <p style="margin: 0; opacity: 0.9; font-size: 14px;">
                    {{ count($n1Issues) }} N+1 {{ count($n1Issues) === 1 ? 'issue' : 'issues' }} detected |
                    {{ $stats['total_queries'] }} queries in {{ number_format($stats['total_time'], 2) }}ms
                    @if($stats['slow_queries'] > 0)
                        | {{ $stats['slow_queries'] }} slow {{ $stats['slow_queries'] === 1 ? 'query' : 'queries' }}
                    @endif
                </p>
            </div>
            <button onclick="document.getElementById('query-debugger-banner').remove()"
                    style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                Close
            </button>
        </div>

        @foreach($suggestions as $index => $suggestion)
        <div style="background: rgba(255,255,255,0.15); border-radius: 8px; padding: 15px; margin-bottom: 12px; backdrop-filter: blur(10px);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="margin: 0; font-size: 16px; font-weight: 600;">
                    Issue #{{ $index + 1 }}: {{ $suggestion['title'] }}
                </h4>
                <span style="background: rgba(255,255,255,0.25); padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                    {{ $suggestion['impact']['query_reduction'] }}% fewer queries
                </span>
            </div>

            <p style="margin: 0 0 12px 0; opacity: 0.95; font-size: 14px; line-height: 1.5;">
                {{ $suggestion['description'] }}
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px; opacity: 0.8;">❌ Current Code:</div>
                    <pre style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 6px; margin: 0; overflow-x: auto; font-size: 13px; line-height: 1.4;"><code>{{ $suggestion['before_code'] }}</code></pre>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 600; margin-bottom: 6px; opacity: 0.8;">✅ Optimized Code:</div>
                    <pre style="background: rgba(0,0,0,0.3); padding: 12px; border-radius: 6px; margin: 0; overflow-x: auto; font-size: 13px; line-height: 1.4;"><code>{{ $suggestion['after_code'] }}</code></pre>
                </div>
            </div>

            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2);">
                <div style="font-size: 12px; opacity: 0.9;">
                    <strong>Impact:</strong>
                    {{ $suggestion['impact']['queries_before'] }} queries → {{ $suggestion['impact']['queries_after'] }} queries
                    @if(isset($suggestion['impact']['estimated_time_saved']))
                        | Save ~{{ $suggestion['impact']['estimated_time_saved'] }}
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 13px; opacity: 0.85; text-align: center;">
            💡 This warning only appears in development mode. Run <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px;">php artisan debug:queries</code> for detailed analysis.
        </div>
    </div>
</div>
