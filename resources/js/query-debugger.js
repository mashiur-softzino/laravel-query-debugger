/**
 * Laravel Query Debugger - Universal Client
 * Works with Blade, Inertia, Vue, React, APIs
 */
(function() {
    'use strict';

    // Only run in development
    if (!document.querySelector('meta[name="query-debugger-enabled"]')) {
        return;
    }

    class QueryDebugger {
        constructor() {
            this.isCollapsed = true;
            this.height = 300;
            this.data = null;
            this.init();
        }

        init() {
            this.createPanel();
            this.fetchDebugData();
            this.attachEventListeners();
            this.observeNavigation();
        }

        fetchDebugData() {
            // Read from meta tag injected by middleware
            const metaData = document.querySelector('meta[name="query-debugger-data"]');
            if (metaData) {
                try {
                    this.data = JSON.parse(metaData.content);
                    this.updatePanel();
                } catch (e) {
                    console.error('Query Debugger: Failed to parse data', e);
                }
            }
        }

        createPanel() {
            const panel = document.createElement('div');
            panel.id = 'query-debugger-toolbar';
            panel.innerHTML = `
                <style>
                    #query-debugger-toolbar {
                        position: fixed;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        z-index: 999999;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                        transition: all 0.3s ease;
                    }

                    #qd-bar {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 8px 16px;
                        cursor: pointer;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
                        user-select: none;
                    }

                    #qd-bar:hover {
                        background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
                    }

                    #qd-stats {
                        display: flex;
                        gap: 20px;
                        font-size: 13px;
                        font-weight: 500;
                    }

                    #qd-stat-item {
                        display: flex;
                        align-items: center;
                        gap: 5px;
                    }

                    .qd-badge {
                        background: rgba(255,255,255,0.25);
                        padding: 2px 8px;
                        border-radius: 10px;
                        font-size: 11px;
                        font-weight: bold;
                    }

                    .qd-badge.error {
                        background: #ef4444;
                    }

                    .qd-badge.warning {
                        background: #f59e0b;
                    }

                    .qd-badge.success {
                        background: #10b981;
                    }

                    #qd-content {
                        background: #1a1a2e;
                        color: #fff;
                        height: 0;
                        overflow: hidden;
                        transition: height 0.3s ease;
                    }

                    #qd-content.expanded {
                        height: 300px;
                        overflow-y: auto;
                        border-top: 2px solid #667eea;
                    }

                    #qd-content-inner {
                        padding: 20px;
                    }

                    .qd-section {
                        margin-bottom: 20px;
                    }

                    .qd-section-title {
                        font-size: 14px;
                        font-weight: bold;
                        margin-bottom: 10px;
                        color: #667eea;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }

                    .qd-metric {
                        display: flex;
                        justify-content: space-between;
                        padding: 8px 12px;
                        background: rgba(255,255,255,0.05);
                        border-radius: 4px;
                        margin-bottom: 8px;
                    }

                    .qd-metric-label {
                        opacity: 0.7;
                        font-size: 13px;
                    }

                    .qd-metric-value {
                        font-weight: bold;
                        font-size: 13px;
                    }

                    .qd-issue-card {
                        background: rgba(239, 68, 68, 0.1);
                        border-left: 3px solid #ef4444;
                        padding: 12px;
                        border-radius: 4px;
                        margin-bottom: 12px;
                    }

                    .qd-code {
                        background: rgba(0,0,0,0.5);
                        padding: 10px;
                        border-radius: 4px;
                        font-family: 'Monaco', 'Courier New', monospace;
                        font-size: 11px;
                        margin: 8px 0;
                        overflow-x: auto;
                        white-space: pre-wrap;
                    }

                    .qd-impact {
                        display: inline-block;
                        background: rgba(16, 185, 129, 0.2);
                        color: #10b981;
                        padding: 4px 8px;
                        border-radius: 4px;
                        font-size: 12px;
                        font-weight: bold;
                        margin-top: 8px;
                    }

                    #qd-resize-handle {
                        height: 4px;
                        background: #667eea;
                        cursor: ns-resize;
                        position: relative;
                    }

                    #qd-resize-handle:hover {
                        background: #5568d3;
                    }
                </style>

                <div id="qd-resize-handle"></div>
                <div id="qd-bar">
                    <div id="qd-stats">
                        <div>
                            <strong>⚡ Query Debugger</strong>
                        </div>
                        <div id="qd-stat-item">
                            <span>Queries:</span>
                            <span class="qd-badge" id="qd-query-count">0</span>
                        </div>
                        <div id="qd-stat-item">
                            <span>Time:</span>
                            <span class="qd-badge" id="qd-query-time">0ms</span>
                        </div>
                        <div id="qd-stat-item">
                            <span>N+1:</span>
                            <span class="qd-badge success" id="qd-n1-count">0</span>
                        </div>
                    </div>
                    <div>
                        <span id="qd-toggle-icon">▲ Click to expand</span>
                    </div>
                </div>

                <div id="qd-content">
                    <div id="qd-content-inner">
                        <div class="qd-section">
                            <div class="qd-section-title">Statistics</div>
                            <div id="qd-statistics"></div>
                        </div>

                        <div class="qd-section" id="qd-issues-section" style="display:none;">
                            <div class="qd-section-title">N+1 Issues</div>
                            <div id="qd-issues"></div>
                        </div>

                        <div class="qd-section" id="qd-other-issues-section" style="display:none;">
                            <div class="qd-section-title">Other Issues</div>
                            <div id="qd-other-issues"></div>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(panel);
        }

        updatePanel() {
            if (!this.data) return;

            const { stats, n1Issues = [], suggestions = [], otherIssues = {} } = this.data;

            // Calculate performance score
            const performanceScore = this.calculatePerformanceScore(stats, n1Issues, otherIssues);

            // Update bar stats
            document.getElementById('qd-query-count').textContent = stats.total_queries;
            document.getElementById('qd-query-count').className =
                'qd-badge ' + (stats.total_queries > 10 ? 'warning' : 'success');

            document.getElementById('qd-query-time').textContent = stats.total_time.toFixed(2) + 'ms';
            document.getElementById('qd-query-time').className =
                'qd-badge ' + (stats.total_time > 100 ? 'warning' : 'success');

            document.getElementById('qd-n1-count').textContent = n1Issues.length;
            document.getElementById('qd-n1-count').className =
                'qd-badge ' + (n1Issues.length > 0 ? 'error' : 'success');

            // Update statistics section with performance score
            const statsHtml = `
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 11px; opacity: 0.8; margin-bottom: 4px;">PERFORMANCE SCORE</div>
                            <div style="font-size: 32px; font-weight: bold;">${performanceScore.score}/100</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 14px; font-weight: 600; color: ${performanceScore.color};">${performanceScore.grade}</div>
                            <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;">${performanceScore.label}</div>
                        </div>
                    </div>
                </div>

                <div class="qd-metric">
                    <span class="qd-metric-label">Total Queries</span>
                    <span class="qd-metric-value">${stats.total_queries}</span>
                </div>
                <div class="qd-metric">
                    <span class="qd-metric-label">Total Time</span>
                    <span class="qd-metric-value">${stats.total_time.toFixed(2)}ms</span>
                </div>
                <div class="qd-metric">
                    <span class="qd-metric-label">Average Time</span>
                    <span class="qd-metric-value">${stats.avg_time.toFixed(2)}ms</span>
                </div>
                <div class="qd-metric">
                    <span class="qd-metric-label">Fastest Query</span>
                    <span class="qd-metric-value" style="color: #10b981;">${stats.fastest_query?.toFixed(2) || '0.00'}ms</span>
                </div>
                <div class="qd-metric">
                    <span class="qd-metric-label">Slowest Query</span>
                    <span class="qd-metric-value" style="color: ${stats.slowest_query > 50 ? '#ef4444' : '#f59e0b'};">${stats.slowest_query?.toFixed(2) || '0.00'}ms</span>
                </div>
                ${stats.slow_queries > 0 ? `
                <div class="qd-metric">
                    <span class="qd-metric-label">Slow Queries (>50ms)</span>
                    <span class="qd-metric-value" style="color: #ef4444;">${stats.slow_queries}</span>
                </div>
                ` : ''}
            `;
            document.getElementById('qd-statistics').innerHTML = statsHtml;

            // Update N+1 issues section
            if (n1Issues.length > 0) {
                document.getElementById('qd-issues-section').style.display = 'block';

                const issuesHtml = n1Issues.map((issue, index) => {
                    const suggestion = suggestions[index];
                    const location = issue.location || suggestion?.location;
                    const fileName = location?.file ? this.getShortFileName(location.file) : 'Unknown';
                    const lineNumber = location?.line || '?';
                    const methodLocation = location?.location || ''; // class::method() format

                    return `
                        <div class="qd-issue-card">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div style="font-weight: bold; font-size: 14px;">
                                    🚨 N+1 Issue #${index + 1}
                                </div>
                                <div style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    ${issue.count} queries
                                </div>
                            </div>

                            <div style="background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; font-family: Monaco, monospace; font-size: 11px;">
                                <div style="opacity: 0.7; margin-bottom: 2px;">📍 Location:</div>
                                ${methodLocation ? `<div style="color: #3b82f6; margin-bottom: 4px;">${this.escapeHtml(methodLocation)}</div>` : ''}
                                <div style="color: #f59e0b;">${fileName}:${lineNumber}</div>
                            </div>

                            <div style="font-size: 12px; opacity: 0.8; margin-bottom: 8px;">
                                <strong>Pattern:</strong> <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px; font-size: 11px;">${issue.pattern}</code>
                            </div>

                            <div style="display: flex; gap: 10px; margin: 10px 0; font-size: 12px;">
                                <div>⏱️ Total: <strong>${issue.total_time?.toFixed(2) || '0.00'}ms</strong></div>
                                <div>📊 Avg: <strong>${issue.avg_time?.toFixed(2) || '0.00'}ms</strong></div>
                            </div>

                            ${suggestion ? `
                                <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px; margin-top: 12px;">
                                    <div style="font-size: 11px; opacity: 0.6; margin-bottom: 6px;">💡 RECOMMENDATION</div>
                                    <div style="font-size: 13px; margin-bottom: 10px; line-height: 1.4;">${suggestion.suggestion || 'Use eager loading to reduce queries'}</div>

                                    ${suggestion.code_example ? `
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px;">
                                            <div>
                                                <div style="font-size: 10px; opacity: 0.6; margin-bottom: 4px;">❌ BEFORE</div>
                                                <div class="qd-code" style="font-size: 10px;">${this.escapeHtml(suggestion.code_example.before)}</div>
                                            </div>
                                            <div>
                                                <div style="font-size: 10px; opacity: 0.6; margin-bottom: 4px;">✅ AFTER</div>
                                                <div class="qd-code" style="font-size: 10px;">${this.escapeHtml(suggestion.code_example.after)}</div>
                                            </div>
                                        </div>
                                    ` : ''}

                                    ${suggestion.impact ? `
                                        <div style="margin-top: 12px; padding: 10px; background: rgba(16, 185, 129, 0.15); border-left: 3px solid #10b981; border-radius: 4px;">
                                            <div style="font-size: 11px; font-weight: 600; color: #10b981; margin-bottom: 4px;">📈 EXPECTED IMPACT</div>
                                            <div style="font-size: 12px; color: #10b981;">
                                                ${suggestion.impact.queries_before || issue.count + 1} queries → ${suggestion.impact.queries_after || 2} queries
                                                <strong>(${suggestion.impact.percent_reduction || '67%'} reduction)</strong>
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            ` : ''}
                        </div>
                    `;
                }).join('');

                document.getElementById('qd-issues').innerHTML = issuesHtml;
            }

            // Update Other Issues section (Duplicates, SELECT *, etc.)
            const duplicates = otherIssues.duplicates || [];
            const selectAll = otherIssues.select_all || [];

            if (duplicates.length > 0 || selectAll.length > 0) {
                document.getElementById('qd-other-issues-section').style.display = 'block';

                let otherIssuesHtml = '';

                // Render duplicate queries
                duplicates.forEach((duplicate, index) => {
                    const severityColor = duplicate.severity === 'high' ? '#ef4444' :
                                         duplicate.severity === 'medium' ? '#f59e0b' : '#fbbf24';
                    const fileName = duplicate.file ? this.getShortFileName(duplicate.file) : 'Unknown';
                    const methodLocation = duplicate.location || '';

                    otherIssuesHtml += `
                        <div class="qd-issue-card" style="border-left-color: ${severityColor};">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div style="font-weight: bold; font-size: 14px;">
                                    🔄 Duplicate Query #${index + 1}
                                </div>
                                <div style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    ${duplicate.count}x executed
                                </div>
                            </div>

                            <div style="background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; font-family: Monaco, monospace; font-size: 11px;">
                                <div style="opacity: 0.7; margin-bottom: 2px;">📍 Location:</div>
                                ${methodLocation ? `<div style="color: #3b82f6; margin-bottom: 4px;">${this.escapeHtml(methodLocation)}</div>` : ''}
                                <div style="color: #f59e0b;">${fileName}:${duplicate.line}</div>
                            </div>

                            <div style="background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; font-family: Monaco, monospace; font-size: 10px; overflow-x: auto;">
                                ${this.escapeHtml(duplicate.sql)}
                            </div>

                            <div style="display: flex; gap: 15px; margin: 10px 0; font-size: 12px;">
                                <div>⏱️ Total: <strong>${duplicate.total_time?.toFixed(2)}ms</strong></div>
                                <div>📊 Avg: <strong>${duplicate.avg_time?.toFixed(2)}ms</strong></div>
                                <div style="color: #ef4444;">💸 Wasted: <strong>${duplicate.wasted_time?.toFixed(2)}ms</strong></div>
                            </div>

                            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px; margin-top: 12px;">
                                <div style="font-size: 11px; opacity: 0.6; margin-bottom: 6px;">💡 RECOMMENDATION</div>
                                <div style="font-size: 13px; line-height: 1.4;">
                                    Cache this query result or refactor to avoid repeated execution. Consider using Laravel's cache or memoization.
                                </div>
                                <div style="margin-top: 12px; padding: 10px; background: rgba(245, 158, 11, 0.15); border-left: 3px solid #f59e0b; border-radius: 4px;">
                                    <div style="font-size: 11px; font-weight: 600; color: #f59e0b; margin-bottom: 4px;">⚡ POTENTIAL SAVINGS</div>
                                    <div style="font-size: 12px; color: #f59e0b;">
                                        Execute once and cache: <strong>${duplicate.count} queries → 1 query</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                // Render SELECT * warnings
                selectAll.forEach((issue, index) => {
                    const fileName = issue.file ? this.getShortFileName(issue.file) : 'Unknown';
                    const methodLocation = issue.location || '';

                    otherIssuesHtml += `
                        <div class="qd-issue-card" style="border-left-color: #fbbf24; background: rgba(251, 191, 36, 0.05);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div style="font-weight: bold; font-size: 14px;">
                                    ⚠️ SELECT * Query #${index + 1}
                                </div>
                                <div style="background: rgba(251, 191, 36, 0.2); color: #fbbf24; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                                    LOW PRIORITY
                                </div>
                            </div>

                            <div style="background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; font-family: Monaco, monospace; font-size: 11px;">
                                <div style="opacity: 0.7; margin-bottom: 2px;">📍 Location:</div>
                                ${methodLocation ? `<div style="color: #3b82f6; margin-bottom: 4px;">${this.escapeHtml(methodLocation)}</div>` : ''}
                                <div style="color: #f59e0b;">${fileName}:${issue.line}</div>
                            </div>

                            <div style="background: rgba(0,0,0,0.3); padding: 8px 10px; border-radius: 4px; margin-bottom: 10px; font-family: Monaco, monospace; font-size: 10px; overflow-x: auto;">
                                ${this.escapeHtml(issue.sql)}
                            </div>

                            <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px; margin-top: 12px;">
                                <div style="font-size: 11px; opacity: 0.6; margin-bottom: 6px;">💡 RECOMMENDATION</div>
                                <div style="font-size: 13px; line-height: 1.4;">
                                    Avoid SELECT * and specify only the columns you need. This reduces memory usage and network transfer.
                                </div>
                                <div style="margin-top: 12px;">
                                    <div style="font-size: 10px; opacity: 0.6; margin-bottom: 4px;">✅ BETTER APPROACH</div>
                                    <div class="qd-code" style="font-size: 10px;">
                                        // Instead of: Model::all()
                                        Model::select(['id', 'name', 'email'])->get()
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                document.getElementById('qd-other-issues').innerHTML = otherIssuesHtml;
            }
        }

        calculatePerformanceScore(stats, n1Issues, otherIssues = {}) {
            let score = 100;
            const duplicates = otherIssues.duplicates || [];
            const selectAll = otherIssues.select_all || [];

            // Deduct for too many queries (max -30 points)
            if (stats.total_queries > 50) {
                score -= 30;
            } else if (stats.total_queries > 30) {
                score -= 20;
            } else if (stats.total_queries > 15) {
                score -= 15;
            } else if (stats.total_queries > 10) {
                score -= 10;
            } else if (stats.total_queries > 5) {
                score -= 5;
            }

            // Deduct for slow queries (max -25 points)
            if (stats.slow_queries > 0) {
                score -= Math.min(stats.slow_queries * 10, 25);
            }

            // Deduct for total time (max -20 points)
            if (stats.total_time > 1000) {
                score -= 20;
            } else if (stats.total_time > 500) {
                score -= 15;
            } else if (stats.total_time > 200) {
                score -= 10;
            } else if (stats.total_time > 100) {
                score -= 5;
            }

            // Deduct for N+1 issues (CRITICAL - max -35 points)
            if (n1Issues.length > 0) {
                score -= Math.min(n1Issues.length * 20, 35);
            }

            // Deduct for duplicate queries (max -25 points)
            if (duplicates.length > 0) {
                score -= Math.min(duplicates.length * 5, 25);
            }

            // Deduct for SELECT * queries - Balanced approach
            // SELECT * is sometimes acceptable, but excessive use is bad
            if (selectAll.length > 0) {
                // Only penalize if excessive (context-dependent)
                if (selectAll.length >= 15) {
                    score -= 15; // Very many SELECT * = poor code quality
                } else if (selectAll.length >= 10) {
                    score -= 10; // Many SELECT * queries
                } else if (selectAll.length >= 5) {
                    score -= 5; // Some SELECT * queries
                }
                // 1-4 SELECT * = acceptable, no penalty (might be small tables or valid use case)
            }

            // CRITICAL PENALTY: Multiple severe issues together
            const hasSevereIssues = n1Issues.length > 0 && duplicates.length >= 5;
            const hasManyQueries = stats.total_queries > 20;

            if (hasSevereIssues && hasManyQueries) {
                // This is a critical/worst-case scenario
                score -= 10; // Extra penalty for multiple severe issues
            }

            // Ensure score is between 0-100
            score = Math.max(0, Math.min(100, score));

            // Determine grade and color
            let grade, label, color;
            if (score >= 90) {
                grade = 'A+';
                label = 'Excellent';
                color = '#10b981';
            } else if (score >= 80) {
                grade = 'A';
                label = 'Very Good';
                color = '#34d399';
            } else if (score >= 70) {
                grade = 'B';
                label = 'Good';
                color = '#fbbf24';
            } else if (score >= 60) {
                grade = 'C';
                label = 'Fair';
                color = '#f59e0b';
            } else if (score >= 50) {
                grade = 'D';
                label = 'Poor';
                color = '#fb923c';
            } else {
                grade = 'F';
                label = 'Critical';
                color = '#ef4444';
            }

            return { score, grade, label, color };
        }

        getShortFileName(fullPath) {
            if (!fullPath) return 'Unknown';
            const parts = fullPath.split('/');
            // Return last 2-3 parts of path for readability
            if (parts.length > 3) {
                return '.../' + parts.slice(-3).join('/');
            }
            return fullPath;
        }

        attachEventListeners() {
            const bar = document.getElementById('qd-bar');
            const content = document.getElementById('qd-content');
            const toggleIcon = document.getElementById('qd-toggle-icon');

            bar.addEventListener('click', () => {
                this.isCollapsed = !this.isCollapsed;

                if (this.isCollapsed) {
                    content.classList.remove('expanded');
                    toggleIcon.textContent = '▲ Click to expand';
                } else {
                    content.classList.add('expanded');
                    toggleIcon.textContent = '▼ Click to collapse';
                }
            });

            // Drag to resize
            const resizeHandle = document.getElementById('qd-resize-handle');
            let isResizing = false;
            let startY = 0;
            let startHeight = 0;

            resizeHandle.addEventListener('mousedown', (e) => {
                isResizing = true;
                startY = e.clientY;
                startHeight = content.offsetHeight;
                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;

                const deltaY = startY - e.clientY;
                const newHeight = Math.min(Math.max(startHeight + deltaY, 100), window.innerHeight - 100);

                if (!this.isCollapsed) {
                    content.style.height = newHeight + 'px';
                }
            });

            document.addEventListener('mouseup', () => {
                isResizing = false;
            });
        }

        observeNavigation() {
            // For SPAs - observe navigation changes
            let lastUrl = location.href;
            new MutationObserver(() => {
                const url = location.href;
                if (url !== lastUrl) {
                    lastUrl = url;
                    setTimeout(() => this.fetchDebugData(), 100);
                }
            }).observe(document, { subtree: true, childList: true });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new QueryDebugger());
    } else {
        new QueryDebugger();
    }
})();
