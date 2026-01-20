/**
 * BarGraph - A fully functional, accessible bar graph component
 * Supports animations, auto-scaling, data binding, and keyboard navigation
 */
class BarGraph {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            animationDuration: 600,
            enableAnimations: true,
            showTooltips: true,
            showLabels: true,
            showLegend: true,
            responsive: true,
            ...options
        };

        this.data = [];
        this.animations = new Map();
        this.resizeObserver = null;
        this.debounceTimer = null;

        this.init();
    }

    init() {
        this.setupContainer();
        this.setupAccessibility();
        this.setupResizeHandling();
        this.render();
    }

    setupContainer() {
        this.container.classList.add('bar-graph-container');
        this.container.setAttribute('role', 'img');
        this.container.setAttribute('aria-label', 'Bar chart visualization');
    }

    setupAccessibility() {
        // Keyboard navigation
        this.container.addEventListener('keydown', (e) => {
            const bars = this.container.querySelectorAll('.bar-graph-bar[tabindex="0"]');
            const currentIndex = Array.from(bars).findIndex(bar => bar === document.activeElement);

            switch(e.key) {
                case 'ArrowRight':
                case 'ArrowDown':
                    e.preventDefault();
                    const nextIndex = Math.min(currentIndex + 1, bars.length - 1);
                    bars[nextIndex]?.focus();
                    break;
                case 'ArrowLeft':
                case 'ArrowUp':
                    e.preventDefault();
                    const prevIndex = Math.max(currentIndex - 1, 0);
                    bars[prevIndex]?.focus();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    this.showTooltip(bars[currentIndex]);
                    break;
                case 'Escape':
                    this.hideAllTooltips();
                    break;
            }
        });

        // Focus management
        this.container.addEventListener('focusin', (e) => {
            if (e.target.classList.contains('bar-graph-bar')) {
                this.showTooltip(e.target);
            }
        });

        this.container.addEventListener('focusout', (e) => {
            this.hideAllTooltips();
        });
    }

    setupResizeHandling() {
        if (window.ResizeObserver && this.options.responsive) {
            this.resizeObserver = new ResizeObserver(() => {
                this.debounceResize();
            });
            this.resizeObserver.observe(this.container);
        }

        window.addEventListener('resize', () => {
            if (this.options.responsive) {
                this.debounceResize();
            }
        });
    }

    debounceResize() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.updateScale();
            this.render();
        }, 100);
    }

    // Data API
    setBarData(dataArray) {
        if (!Array.isArray(dataArray)) {
            console.warn('setBarData expects an array of objects');
            return;
        }

        // Validate and sanitize data
        this.data = dataArray.map(item => {
            if (typeof item !== 'object' || item === null) {
                console.warn('Invalid data item:', item);
                return { label: 'Invalid', value: 0 };
            }

            const value = typeof item.value === 'number' ? item.value :
                         parseFloat(item.value) || 0;

            return {
                label: String(item.label || 'Unknown'),
                value: value,
                originalValue: value
            };
        });

        this.updateScale();
        this.render();
    }

    updateBar(index, newValue) {
        if (index < 0 || index >= this.data.length) {
            console.warn('Invalid index for updateBar');
            return;
        }

        const numericValue = typeof newValue === 'number' ? newValue : parseFloat(newValue) || 0;
        this.data[index].value = numericValue;
        this.data[index].originalValue = numericValue;

        this.updateScale();
        this.animateBar(index);
    }

    addBar(barObj) {
        if (!barObj || typeof barObj !== 'object') {
            console.warn('addBar expects an object with label and value');
            return;
        }

        const newBar = {
            label: String(barObj.label || 'New Bar'),
            value: typeof barObj.value === 'number' ? barObj.value : parseFloat(barObj.value) || 0,
            originalValue: barObj.value
        };

        this.data.push(newBar);
        this.updateScale();
        this.render();
    }

    removeBar(index) {
        if (index < 0 || index >= this.data.length) {
            console.warn('Invalid index for removeBar');
            return;
        }

        this.data.splice(index, 1);
        this.updateScale();
        this.render();
    }

    resetBars() {
        this.data = [];
        this.render();
    }

    getBarData() {
        return this.data.map(item => ({
            label: item.label,
            value: item.originalValue
        }));
    }

    // Scaling logic
    updateScale() {
        if (this.data.length === 0) {
            this.scale = { min: 0, max: 1, range: 1 };
            return;
        }

        const values = this.data.map(d => d.value);
        const min = Math.min(...values, 0); // Include 0 for positive-only charts
        const max = Math.max(...values, 1); // Minimum max of 1

        // Add 10% headroom
        const range = max - min;
        const headroom = range * 0.1;
        const adjustedMax = max + headroom;
        const adjustedMin = min - (range > 0 ? headroom : 0);

        this.scale = {
            min: adjustedMin,
            max: adjustedMax,
            range: adjustedMax - adjustedMin
        };
    }

    getBarHeight(value) {
        if (this.scale.range === 0) return 0;
        const normalized = (value - this.scale.min) / this.scale.range;
        return Math.max(0, Math.min(100, normalized * 100));
    }

    // Animation system
    animateBar(index, targetHeight = null) {
        if (!this.options.enableAnimations) {
            this.render();
            return;
        }

        const bar = this.container.querySelector(`.bar-graph-bar[data-index="${index}"]`);
        if (!bar) return;

        const currentHeight = targetHeight !== null ? targetHeight : this.getBarHeight(this.data[index].value);

        // Cancel existing animation
        if (this.animations.has(index)) {
            cancelAnimationFrame(this.animations.get(index));
        }

        const startHeight = parseFloat(bar.style.height) || 0;
        const startTime = performance.now();
        const duration = this.options.animationDuration;

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Easing function (ease-out cubic)
            const easedProgress = 1 - Math.pow(1 - progress, 3);

            const height = startHeight + (currentHeight - startHeight) * easedProgress;
            bar.style.height = `${height}%`;

            if (progress < 1) {
                this.animations.set(index, requestAnimationFrame(animate));
            } else {
                this.animations.delete(index);
            }
        };

        this.animations.set(index, requestAnimationFrame(animate));
    }

    enableAnimations(enable) {
        this.options.enableAnimations = Boolean(enable);
        if (!enable) {
            // Cancel all animations
            this.animations.forEach((animationId) => {
                cancelAnimationFrame(animationId);
            });
            this.animations.clear();
            this.render();
        }
    }

    // Rendering
    render() {
        this.container.innerHTML = '';

        if (this.data.length === 0) {
            this.renderEmptyState();
            return;
        }

        this.renderBars();
        this.renderLabels();
        this.renderLegend();
    }

    renderEmptyState() {
        const emptyState = document.createElement('div');
        emptyState.className = 'bar-graph-empty';
        emptyState.textContent = 'No data to display';
        emptyState.setAttribute('aria-live', 'polite');
        this.container.appendChild(emptyState);
    }

    renderBars() {
        const barsContainer = document.createElement('div');
        barsContainer.className = 'bar-graph-bars';

        this.data.forEach((item, index) => {
            const barWrapper = document.createElement('div');
            barWrapper.className = 'bar-graph-bar-wrapper';

            const bar = document.createElement('div');
            bar.className = 'bar-graph-bar';
            bar.setAttribute('data-index', index);
            bar.setAttribute('tabindex', '0');
            bar.setAttribute('role', 'button');
            bar.setAttribute('aria-label', `${item.label}: ${item.originalValue}`);
            bar.setAttribute('aria-valuenow', item.originalValue);
            bar.setAttribute('aria-valuemin', this.scale.min);
            bar.setAttribute('aria-valuemax', this.scale.max);

            const height = this.getBarHeight(item.value);
            bar.style.height = this.options.enableAnimations ? '0%' : `${height}%`;

            // Animate if enabled
            if (this.options.enableAnimations) {
                setTimeout(() => this.animateBar(index, height), 50 * index);
            }

            barWrapper.appendChild(bar);
            barsContainer.appendChild(barWrapper);
        });

        this.container.appendChild(barsContainer);
    }

    renderLabels() {
        if (!this.options.showLabels) return;

        const labelsContainer = document.createElement('div');
        labelsContainer.className = 'bar-graph-labels';

        this.data.forEach((item, index) => {
            const label = document.createElement('div');
            label.className = 'bar-graph-label';
            label.textContent = item.label;
            label.setAttribute('aria-hidden', 'true');
            labelsContainer.appendChild(label);
        });

        this.container.appendChild(labelsContainer);
    }

    renderLegend() {
        if (!this.options.showLegend) return;

        const legend = document.createElement('div');
        legend.className = 'bar-graph-legend';
        legend.setAttribute('role', 'list');
        legend.setAttribute('aria-label', 'Chart legend');

        // Y-axis ticks
        const ticks = this.generateTicks();
        ticks.forEach(tick => {
            const tickElement = document.createElement('div');
            tickElement.className = 'bar-graph-tick';
            tickElement.textContent = tick;
            tickElement.setAttribute('aria-hidden', 'true');
            legend.appendChild(tickElement);
        });

        this.container.appendChild(legend);
    }

    generateTicks() {
        const { min, max } = this.scale;
        const range = max - min;
        const tickCount = 5;

        if (range === 0) return [min.toFixed(1)];

        const step = range / (tickCount - 1);
        const ticks = [];

        for (let i = 0; i < tickCount; i++) {
            const value = min + (step * i);
            ticks.push(this.formatNumber(value));
        }

        return ticks;
    }

    formatNumber(num) {
        if (Math.abs(num) >= 1e9) return (num / 1e9).toFixed(1) + 'B';
        if (Math.abs(num) >= 1e6) return (num / 1e6).toFixed(1) + 'M';
        if (Math.abs(num) >= 1e3) return (num / 1e3).toFixed(1) + 'K';
        if (Math.abs(num) < 1 && num !== 0) return num.toFixed(2);
        return num.toFixed(0);
    }

    // Tooltip system
    showTooltip(bar) {
        if (!this.options.showTooltips) return;

        this.hideAllTooltips();

        const index = parseInt(bar.getAttribute('data-index'));
        const item = this.data[index];
        if (!item) return;

        const tooltip = document.createElement('div');
        tooltip.className = 'bar-graph-tooltip';
        tooltip.innerHTML = `
            <strong>${item.label}</strong><br>
            Value: ${this.formatNumber(item.originalValue)}
        `;
        tooltip.setAttribute('role', 'tooltip');

        document.body.appendChild(tooltip);

        // Position tooltip
        const rect = bar.getBoundingClientRect();
        tooltip.style.left = `${rect.left + rect.width / 2}px`;
        tooltip.style.top = `${rect.top - 10}px`;
        tooltip.style.transform = 'translate(-50%, -100%)';

        // Store reference for cleanup
        bar._tooltip = tooltip;
    }

    hideAllTooltips() {
        document.querySelectorAll('.bar-graph-tooltip').forEach(tooltip => {
            tooltip.remove();
        });

        // Clear references
        this.container.querySelectorAll('.bar-graph-bar').forEach(bar => {
            delete bar._tooltip;
        });
    }

    // Cleanup
    destroy() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }

        this.animations.forEach((animationId) => {
            cancelAnimationFrame(animationId);
        });

        this.hideAllTooltips();
        this.container.innerHTML = '';
        this.container.classList.remove('bar-graph-container');
    }
}

// CSS for the bar graph component
const barGraphStyles = `
.bar-graph-container {
    position: relative;
    width: 100%;
    height: 300px;
    margin: 1rem 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.bar-graph-bars {
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    height: 100%;
    gap: 1rem;
    padding: 2rem 0 1rem 0;
}

.bar-graph-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.bar-graph-bar {
    width: 100%;
    max-width: 60px;
    background: linear-gradient(180deg, #3b82f6 0%, #1e40af 100%);
    border-radius: 4px 4px 0 0;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    outline: none;
}

.bar-graph-bar:hover,
.bar-graph-bar:focus {
    transform: scale(1.05);
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.5);
}

.bar-graph-bar:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

.bar-graph-labels {
    display: flex;
    justify-content: space-around;
    margin-top: 0.5rem;
}

.bar-graph-label {
    font-size: 0.875rem;
    color: #64748b;
    text-align: center;
    max-width: 80px;
    word-wrap: break-word;
}

.bar-graph-legend {
    position: absolute;
    left: -60px;
    top: 2rem;
    height: calc(100% - 4rem);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #64748b;
}

.bar-graph-tick {
    position: relative;
}

.bar-graph-tick::after {
    content: '';
    position: absolute;
    right: -10px;
    top: 50%;
    width: 8px;
    height: 1px;
    background: #cbd5e1;
    transform: translateY(-50%);
}

.bar-graph-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #64748b;
    font-style: italic;
}

.bar-graph-tooltip {
    position: fixed;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    pointer-events: none;
    z-index: 1000;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.bar-graph-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: rgba(0, 0, 0, 0.8);
}

@media (max-width: 768px) {
    .bar-graph-container {
        height: 250px;
    }

    .bar-graph-bars {
        gap: 0.5rem;
        padding: 1.5rem 0 1rem 0;
    }

    .bar-graph-bar {
        max-width: 40px;
    }

    .bar-graph-legend {
        display: none;
    }
}
`;

// Inject styles
if (!document.querySelector('#bar-graph-styles')) {
    const style = document.createElement('style');
    style.id = 'bar-graph-styles';
    style.textContent = barGraphStyles;
    document.head.appendChild(style);
}

// Export for use
window.BarGraph = BarGraph;
