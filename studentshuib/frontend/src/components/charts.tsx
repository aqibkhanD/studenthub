'use client';
import React from 'react';

// ============================================================
// Donut — multi-segment ring with a centered label.
// Pure SVG, no external library. Handles empty state cleanly.
// ============================================================

export interface DonutSegment {
  label: string;
  value: number;
  color: string;  // any valid CSS color (hex / oklch / tailwind-resolved)
}

export interface DonutProps {
  segments: DonutSegment[];
  centerValue: number | string;
  centerLabel?: string;   // e.g. "ACTIVE"
  size?: number;          // px (default 180)
  thickness?: number;     // ring thickness in px (default 22)
}

export function Donut({
  segments,
  centerValue,
  centerLabel,
  size = 180,
  thickness = 22,
}: DonutProps) {
  const r          = (size - thickness) / 2;
  const cx         = size / 2;
  const cy         = size / 2;
  const circumference = 2 * Math.PI * r;
  const total      = segments.reduce((sum, s) => sum + s.value, 0);

  // Empty state: render an empty grey ring, no segments
  if (total === 0) {
    return (
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="block">
        <circle
          cx={cx} cy={cy} r={r}
          fill="none"
          stroke="#f3f4f6"
          strokeWidth={thickness}
        />
        <text
          x={cx} y={cy - 4}
          textAnchor="middle"
          className="fill-gray-900"
          style={{ fontSize: size * 0.18, fontWeight: 700 }}
        >
          {centerValue}
        </text>
        {centerLabel && (
          <text
            x={cx} y={cy + size * 0.12}
            textAnchor="middle"
            className="fill-gray-400"
            style={{ fontSize: size * 0.07, letterSpacing: 1, fontWeight: 600 }}
          >
            {centerLabel}
          </text>
        )}
      </svg>
    );
  }

  let cumulative = 0;
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="block">
      {/* Background ring (sits behind segments to round out gaps) */}
      <circle
        cx={cx} cy={cy} r={r}
        fill="none"
        stroke="#f3f4f6"
        strokeWidth={thickness}
      />
      {segments.map((seg, i) => {
        if (seg.value === 0) return null;
        const segLen = (seg.value / total) * circumference;
        const offset = (cumulative / total) * circumference;
        cumulative += seg.value;
        return (
          <circle
            key={`${seg.label}-${i}`}
            cx={cx} cy={cy} r={r}
            fill="none"
            stroke={seg.color}
            strokeWidth={thickness}
            strokeDasharray={`${segLen} ${circumference - segLen}`}
            strokeDashoffset={-offset}
            // Rotate so segments start at the 12 o'clock position
            transform={`rotate(-90 ${cx} ${cy})`}
            style={{ transition: 'stroke-dasharray 300ms ease' }}
          />
        );
      })}
      {/* Center label */}
      <text
        x={cx} y={cy - 4}
        textAnchor="middle"
        className="fill-gray-900"
        style={{ fontSize: size * 0.18, fontWeight: 700 }}
      >
        {centerValue}
      </text>
      {centerLabel && (
        <text
          x={cx} y={cy + size * 0.12}
          textAnchor="middle"
          className="fill-gray-400"
          style={{ fontSize: size * 0.07, letterSpacing: 1, fontWeight: 600 }}
        >
          {centerLabel}
        </text>
      )}
    </svg>
  );
}

// ============================================================
// LineChart — simple inline SVG line with dots and axis labels.
// Designed for small dashboards (5-30 points). Handles empty.
// ============================================================

export interface LinePoint {
  label: string;
  count: number;
}

export interface LineChartProps {
  data:    LinePoint[];
  height?: number;        // overall SVG height in px (default 240)
  color?:  string;        // line + dot color (default brand blue)
  fill?:   boolean;       // shade area under the line (default true)
}

export function LineChart({
  data,
  height = 240,
  color  = '#1e3a8a',
  fill   = true,
}: LineChartProps) {
  // Use a wide viewBox for crisp rendering at any container width
  const width      = 800;
  const padTop     = 12;
  const padBottom  = 28;
  const padLeft    = 44;
  const padRight   = 12;
  const chartW     = width  - padLeft - padRight;
  const chartH     = height - padTop  - padBottom;

  // Empty state
  if (data.length === 0) {
    return (
      <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="w-full h-full">
        <text x={width/2} y={height/2} textAnchor="middle" className="fill-gray-400" style={{ fontSize: 14 }}>
          No data for this period
        </text>
      </svg>
    );
  }

  const maxValue = Math.max(...data.map(d => d.count), 1);
  // Pad max up so the line doesn't touch the top edge
  const yMax     = Math.ceil(maxValue * 1.1);

  // Y-axis: 5 gridlines (0, 25%, 50%, 75%, 100%)
  const ySteps = [0, 0.25, 0.5, 0.75, 1].map(t => Math.round(yMax * t));

  // X positions: evenly spaced
  const xFor = (i: number) => padLeft + (data.length === 1 ? chartW/2 : (i / (data.length - 1)) * chartW);
  const yFor = (v: number) => padTop + chartH - (v / yMax) * chartH;

  // Build the line path
  const linePath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i)} ${yFor(d.count)}`).join(' ');

  // Build the area fill path (line + bottom edge)
  const areaPath = `${linePath} L ${xFor(data.length - 1)} ${padTop + chartH} L ${xFor(0)} ${padTop + chartH} Z`;

  return (
    <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="w-full h-full">
      {/* Y-axis gridlines + labels */}
      {ySteps.map((v) => {
        const y = yFor(v);
        return (
          <g key={v}>
            <line
              x1={padLeft} y1={y} x2={width - padRight} y2={y}
              stroke="#f3f4f6" strokeWidth={1}
            />
            <text
              x={padLeft - 8} y={y + 4}
              textAnchor="end"
              className="fill-gray-400"
              style={{ fontSize: 11 }}
            >
              {v}
            </text>
          </g>
        );
      })}

      {/* Area fill */}
      {fill && (
        <path
          d={areaPath}
          fill={color}
          fillOpacity={0.07}
        />
      )}

      {/* Line */}
      <path
        d={linePath}
        fill="none"
        stroke={color}
        strokeWidth={2.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />

      {/* Dots */}
      {data.map((d, i) => (
        <circle
          key={d.label + i}
          cx={xFor(i)} cy={yFor(d.count)}
          r={4}
          fill={color}
        />
      ))}

      {/* X-axis labels */}
      {data.map((d, i) => (
        <text
          key={`x-${d.label}-${i}`}
          x={xFor(i)} y={height - 8}
          textAnchor="middle"
          className="fill-gray-400"
          style={{ fontSize: 11 }}
        >
          {d.label}
        </text>
      ))}
    </svg>
  );
}
