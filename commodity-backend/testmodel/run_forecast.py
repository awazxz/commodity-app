#!/usr/bin/env python3
"""
run_forecast.py - Standalone Forecast Visualizer
=================================================
Jalankan Prophet forecast langsung dari CSV, tanpa Flask API.
Output: HTML report interaktif + PNG chart per komoditas.

Struktur folder:
    testback/
    +-- run_forecast.py       <- script ini
    +-- data/
    |   +-- data_harga.csv    <- taruh CSV kamu di sini
    +-- output/
        +-- charts/           <- PNG per komoditas (auto dibuat)
        +-- reports/          <- HTML report (auto dibuat)

Usage:
    python run_forecast.py
    python run_forecast.py --file data/data_harga.csv
    python run_forecast.py --file data/data_harga.csv --periods 12
    python run_forecast.py --file data/data_harga.csv --open
    python run_forecast.py --list

Format CSV wajib punya kolom:
    id, tanggal, nama_komoditas, harga

Requirements:
    pip install prophet plotly rich matplotlib pandas
"""

import argparse
import sys
import warnings
import webbrowser
from datetime import datetime
from pathlib import Path

warnings.filterwarnings("ignore")

# --- Cek dependencies ---
def _check_deps():
    missing = []
    for pkg, imp in [("prophet","prophet"),("plotly","plotly"),
                     ("rich","rich"),("matplotlib","matplotlib"),("pandas","pandas")]:
        try:
            __import__(imp)
        except ImportError:
            missing.append(pkg)
    if missing:
        print(f"\n[ERROR] Package belum terinstall: {', '.join(missing)}")
        print(f"   Jalankan: pip install {' '.join(missing)}\n")
        sys.exit(1)

_check_deps()

import numpy as np
import pandas as pd
import plotly.graph_objects as go
import plotly.io as pio
from plotly.subplots import make_subplots
from prophet import Prophet
from rich.console import Console
from rich.panel import Panel
from rich.progress import Progress, SpinnerColumn, TextColumn, BarColumn, TimeElapsedColumn
from rich.rule import Rule
from rich.table import Table

console = Console()

# ----------------------------------------------------------------
# KONSTANTA - identik dengan prophet_forecasting.py v10.2
# ----------------------------------------------------------------

DEFAULT_HYPERPARAMS = {
    "changepoint_prior_scale": 0.1,
    "seasonality_prior_scale": 10.0,
    "seasonality_mode":        "additive",
    "weekly_seasonality":      False,
    "yearly_seasonality":      True,
    "yearly_fourier_order":    20,
    "monthly_seasonality":     True,
    "n_changepoints":          25,
    "changepoint_range":       0.85,
    "interval_width":          0.80,
}

COMMODITY_COLORS = [
    "#3B82F6","#10B981","#F59E0B","#EF4444","#8B5CF6",
    "#06B6D4","#F97316","#84CC16","#EC4899","#14B8A6",
    "#6366F1","#FB923C","#A855F7",
]

BASE_DIR    = Path(__file__).parent
DATA_DIR    = BASE_DIR / "data"
OUTPUT_DIR  = BASE_DIR / "output"
CHARTS_DIR  = OUTPUT_DIR / "charts"
REPORTS_DIR = OUTPUT_DIR / "reports"

for d in [DATA_DIR, OUTPUT_DIR, CHARTS_DIR, REPORTS_DIR]:
    d.mkdir(parents=True, exist_ok=True)


# ----------------------------------------------------------------
# DATA LOADER
# ----------------------------------------------------------------

def load_csv(filepath: Path) -> pd.DataFrame:
    for enc in ("utf-8","utf-8-sig","latin-1","cp1252"):
        try:
            df = pd.read_csv(filepath, encoding=enc)
            break
        except Exception:
            continue
    else:
        console.print(f"[red]Gagal membaca {filepath}[/red]")
        sys.exit(1)

    df.columns = df.columns.str.strip().str.lower()
    required = {"tanggal","nama_komoditas","harga"}
    missing  = required - set(df.columns)
    if missing:
        console.print(f"[red]Kolom tidak ditemukan: {missing}[/red]")
        console.print(f"   Kolom yang ada: {list(df.columns)}")
        sys.exit(1)

    df["tanggal"] = pd.to_datetime(df["tanggal"], errors="coerce")
    df["harga"]   = pd.to_numeric(
        df["harga"].astype(str).str.replace(",","").str.replace("Rp","").str.strip(),
        errors="coerce"
    )
    df = df.dropna(subset=["tanggal","harga"])
    df = df[df["harga"] > 0]
    df["nama_komoditas"] = df["nama_komoditas"].str.strip()
    return df


def prepare_prophet_df(df: pd.DataFrame) -> pd.DataFrame:
    df = df.sort_values("tanggal").copy()
    df = df.set_index("tanggal")
    weekly = df["harga"].resample("W").mean().dropna().reset_index()
    weekly.columns = ["ds","y"]
    return weekly


# ----------------------------------------------------------------
# PROPHET ENGINE - identik dengan prophet_forecasting.py v10.2
# ----------------------------------------------------------------

def build_prophet(hp: dict = None) -> Prophet:
    p = {**DEFAULT_HYPERPARAMS, **(hp or {})}
    m = Prophet(
        changepoint_prior_scale = p["changepoint_prior_scale"],
        seasonality_prior_scale = p["seasonality_prior_scale"],
        seasonality_mode        = p["seasonality_mode"],
        weekly_seasonality      = p["weekly_seasonality"],
        yearly_seasonality      = False,
        daily_seasonality       = False,
        interval_width          = p["interval_width"],
        n_changepoints          = p["n_changepoints"],
        changepoint_range       = p["changepoint_range"],
    )
    if p["yearly_seasonality"]:
        m.add_seasonality(name="yearly",  period=365.25, fourier_order=p["yearly_fourier_order"])
    if p["monthly_seasonality"]:
        m.add_seasonality(name="monthly", period=30.5,   fourier_order=5)
    return m


def compute_metrics(actual, predicted, lower, upper) -> dict:
    actual    = np.array(actual,    dtype=float)
    predicted = np.array(predicted, dtype=float)
    lower     = np.array(lower,     dtype=float)
    upper     = np.array(upper,     dtype=float)

    mask  = actual != 0
    mape  = float(np.mean(np.abs((actual[mask]-predicted[mask])/actual[mask]))*100) if mask.any() else 0.0
    denom = (np.abs(actual)+np.abs(predicted))/2
    sm    = denom > 0
    smape = float(np.mean(np.abs(actual[sm]-predicted[sm])/denom[sm])*100) if sm.any() else 0.0
    rmse  = float(np.sqrt(np.mean((actual-predicted)**2)))
    mae   = float(np.mean(np.abs(actual-predicted)))
    dir_acc = float(np.mean(np.sign(np.diff(actual))==np.sign(np.diff(predicted)))*100) if len(actual)>1 else 0.0
    coverage = float(np.mean((actual>=lower)&(actual<=upper)))

    return {
        "mape":     round(mape,    4),
        "smape":    round(smape,   4),
        "rmse":     round(rmse,    2),
        "mae":      round(mae,     2),
        "dir_acc":  round(dir_acc, 2),
        "coverage": round(coverage,4),
    }


def run_single_forecast(df_prophet: pd.DataFrame, periods: int = 12) -> dict:
    n      = len(df_prophet)
    test_n = max(4, int(n*0.20))
    train_n= n - test_n

    # Walk-forward CV 80/20
    metrics = {}
    if train_n >= 8:
        train_cv = df_prophet.iloc[:train_n].copy()
        test_cv  = df_prophet.iloc[train_n:].reset_index(drop=True).copy()
        m_cv = build_prophet()
        m_cv.fit(train_cv)
        fut_cv = m_cv.make_future_dataframe(periods=test_n+8, freq="W")
        fc_cv  = m_cv.predict(fut_cv)
        fc_cv  = fc_cv[fc_cv["ds"] > train_cv["ds"].max()].reset_index(drop=True)
        merged = pd.merge_asof(
            test_cv.sort_values("ds"),
            fc_cv[["ds","yhat","yhat_lower","yhat_upper"]].sort_values("ds"),
            on="ds", direction="nearest", tolerance=pd.Timedelta("3 days"),
        ).dropna(subset=["yhat"])
        if len(merged) > 0:
            metrics = compute_metrics(
                merged["y"].values, merged["yhat"].values,
                merged["yhat_lower"].values, merged["yhat_upper"].values,
            )

    # Full model
    m = build_prophet()
    m.fit(df_prophet)
    last_date = df_prophet["ds"].max()
    future    = m.make_future_dataframe(periods=periods+8, freq="W")
    fc_all    = m.predict(future)

    forecast_df = fc_all[fc_all["ds"] > last_date].head(periods).reset_index(drop=True)
    fitted_df   = fc_all[fc_all["ds"] <= last_date].reset_index(drop=True)

    if len(forecast_df) >= 2:
        first_v = forecast_df["yhat"].iloc[0]
        last_v  = forecast_df["yhat"].iloc[-1]
        change  = (last_v - first_v)/(first_v+1e-8)*100
        trend   = "increasing" if change>1 else "decreasing" if change<-1 else "stable"
    else:
        trend = "stable"

    return {
        "forecast_df": forecast_df,
        "fitted_df":   fitted_df,
        "metrics":     metrics,
        "trend":       trend,
        "last_date":   last_date,
    }


# ----------------------------------------------------------------
# PLOTLY CHART BUILDER
# ----------------------------------------------------------------

def _hex_to_rgb(hex_color: str) -> str:
    h = hex_color.lstrip("#")
    return f"{int(h[0:2],16)},{int(h[2:4],16)},{int(h[4:6],16)}"


def build_plotly_figure(commodity_name, df_hist, fitted_df, forecast_df, metrics, color):
    fig = make_subplots(
        rows=2, cols=1,
        row_heights=[0.75, 0.25],
        shared_xaxes=True,
        vertical_spacing=0.08,
        subplot_titles=["Harga Historis & Forecast","Residual (Aktual - Fitted)"],
    )

    # 1. Confidence interval shaded
    if not forecast_df.empty:
        x_shade = list(forecast_df["ds"].astype(str)) + list(forecast_df["ds"].astype(str))[::-1]
        y_shade = list(forecast_df["yhat_upper"]) + list(forecast_df["yhat_lower"])[::-1]
        fig.add_trace(go.Scatter(
            x=x_shade, y=y_shade, fill="toself",
            fillcolor=f"rgba({_hex_to_rgb(color)},0.15)",
            line=dict(color="rgba(0,0,0,0)"),
            name="Interval Kepercayaan 80%", hoverinfo="skip",
        ), row=1, col=1)

    # 2. Historis aktual
    fig.add_trace(go.Scatter(
        x=df_hist["ds"].astype(str), y=df_hist["y"],
        mode="lines+markers", name="Harga Aktual",
        line=dict(color="#64748B", width=1.5), marker=dict(size=3),
        hovertemplate="<b>%{x}</b><br>Aktual: Rp %{y:,.0f}<extra></extra>",
    ), row=1, col=1)

    # 3. Fitted values + residual
    if not fitted_df.empty:
        merged_fit = pd.merge_asof(
            df_hist.sort_values("ds"),
            fitted_df[["ds","yhat"]].sort_values("ds"),
            on="ds", direction="nearest", tolerance=pd.Timedelta("3 days"),
        ).dropna(subset=["yhat"])
        if not merged_fit.empty:
            fig.add_trace(go.Scatter(
                x=merged_fit["ds"].astype(str), y=merged_fit["yhat"],
                mode="lines", name="Fitted (In-Sample)",
                line=dict(color=color, width=1.5, dash="dot"),
                hovertemplate="<b>%{x}</b><br>Fitted: Rp %{y:,.0f}<extra></extra>",
            ), row=1, col=1)
            residual   = merged_fit["y"] - merged_fit["yhat"]
            colors_res = ["#EF4444" if r<0 else "#10B981" for r in residual]
            fig.add_trace(go.Bar(
                x=merged_fit["ds"].astype(str), y=residual,
                name="Residual", marker_color=colors_res,
                hovertemplate="<b>%{x}</b><br>Residual: Rp %{y:,.0f}<extra></extra>",
            ), row=2, col=1)
            fig.add_hline(y=0, line_dash="dash", line_color="#94A3B8", line_width=1, row=2, col=1)

    # 4. Forecast line + bounds
    if not forecast_df.empty:
        fig.add_trace(go.Scatter(
            x=forecast_df["ds"].astype(str), y=forecast_df["yhat"],
            mode="lines+markers", name="Forecast",
            line=dict(color=color, width=2.5), marker=dict(size=5, color=color),
            hovertemplate="<b>%{x}</b><br>Prediksi: Rp %{y:,.0f}<extra></extra>",
        ), row=1, col=1)
        fig.add_trace(go.Scatter(
            x=forecast_df["ds"].astype(str), y=forecast_df["yhat_upper"],
            mode="lines", showlegend=False,
            line=dict(color=color, width=0.8, dash="dash"),
            hovertemplate="<b>%{x}</b><br>Upper: Rp %{y:,.0f}<extra></extra>",
        ), row=1, col=1)
        fig.add_trace(go.Scatter(
            x=forecast_df["ds"].astype(str), y=forecast_df["yhat_lower"],
            mode="lines", showlegend=False,
            line=dict(color=color, width=0.8, dash="dash"),
            hovertemplate="<b>%{x}</b><br>Lower: Rp %{y:,.0f}<extra></extra>",
        ), row=1, col=1)

    # 5. Vertical line batas historis/forecast (pakai add_shape - aman untuk subplot)
    if not df_hist.empty and not forecast_df.empty:
        last_hist_ts = df_hist["ds"].max()
        fig.add_shape(
            type="line",
            x0=last_hist_ts, x1=last_hist_ts,
            y0=0, y1=1,
            xref="x", yref="paper",
            line=dict(color="#F59E0B", width=2, dash="dash"),
        )
        fig.add_annotation(
            x=last_hist_ts, y=0.97,
            xref="x", yref="paper",
            text="  Data Terakhir",
            showarrow=False,
            font=dict(color="#F59E0B", size=11),
            xanchor="left", yanchor="top",
            bgcolor="rgba(255,255,255,0.8)",
        )

    mape_str = f"{metrics.get('mape',0):.2f}%" if metrics else "N/A"
    dir_str  = f"{metrics.get('dir_acc',0):.1f}%" if metrics else "N/A"

    fig.update_layout(
        title=dict(
            text=(f"<b>{commodity_name}</b><br>"
                  f"<span style='font-size:13px;color:#64748B'>"
                  f"MAPE: {mape_str} | Directional Accuracy: {dir_str} | Interval: 80%</span>"),
            font=dict(size=18), x=0.01,
        ),
        height=680, hovermode="x unified",
        legend=dict(orientation="h", yanchor="bottom", y=1.01, xanchor="left", x=0, font=dict(size=12)),
        paper_bgcolor="#FFFFFF", plot_bgcolor="#F8FAFC",
        font=dict(family="Inter, system-ui, sans-serif"),
        margin=dict(l=60, r=40, t=100, b=40),
    )
    fig.update_xaxes(showgrid=True, gridcolor="#E2E8F0", showline=True,
                     linecolor="#CBD5E1", tickformat="%b %Y")
    fig.update_yaxes(showgrid=True, gridcolor="#E2E8F0",
                     tickformat=",", tickprefix="Rp ", row=1, col=1)
    fig.update_yaxes(showgrid=True, gridcolor="#E2E8F0",
                     tickformat=",", tickprefix="Rp ", row=2, col=1)
    return fig


# ----------------------------------------------------------------
# PNG SAVER - matplotlib (tidak butuh Chrome/kaleido)
# ----------------------------------------------------------------

def save_png_matplotlib(commodity_name, df_hist, fitted_df, forecast_df,
                        metrics, trend, color, output_path):
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
    import matplotlib.dates as mdates
    import matplotlib.ticker as mticker
    from matplotlib.gridspec import GridSpec

    try:
        h = color.lstrip("#")
        rc, gc, bc = int(h[0:2],16)/255, int(h[2:4],16)/255, int(h[4:6],16)/255
        main_c  = (rc, gc, bc)
        light_c = (rc, gc, bc, 0.15)

        fig = plt.figure(figsize=(14, 8), facecolor="white")
        gs  = GridSpec(2, 1, height_ratios=[3,1], hspace=0.06, figure=fig)
        ax1 = fig.add_subplot(gs[0])
        ax2 = fig.add_subplot(gs[1], sharex=ax1)

        for ax in [ax1, ax2]:
            ax.set_facecolor("#F8FAFC")
            ax.grid(True, color="#E2E8F0", linewidth=0.7, zorder=0)
            ax.spines[["top","right"]].set_visible(False)
            ax.spines[["left","bottom"]].set_color("#CBD5E1")

        # Historis
        ax1.plot(df_hist["ds"], df_hist["y"],
                 color="#64748B", linewidth=1.5, label="Harga Aktual",
                 marker="o", markersize=2.5, zorder=3)

        # Fitted + residual
        if not fitted_df.empty:
            mf = pd.merge_asof(
                df_hist.sort_values("ds"),
                fitted_df[["ds","yhat"]].sort_values("ds"),
                on="ds", direction="nearest", tolerance=pd.Timedelta("3 days"),
            ).dropna(subset=["yhat"])
            if not mf.empty:
                ax1.plot(mf["ds"], mf["yhat"],
                         color=main_c, linewidth=1.5, linestyle="--",
                         label="Fitted (In-Sample)", zorder=4, alpha=0.8)
                residual  = mf["y"] - mf["yhat"]
                bar_colors= ["#EF4444" if r<0 else "#10B981" for r in residual]
                ax2.bar(mf["ds"], residual, color=bar_colors, width=5, alpha=0.75, zorder=3)
                ax2.axhline(0, color="#94A3B8", linewidth=1, linestyle="--")
                ax2.set_ylabel("Residual (Rp)", fontsize=9, color="#64748B")

        # Forecast confidence interval
        if not forecast_df.empty:
            ax1.fill_between(forecast_df["ds"],
                             forecast_df["yhat_lower"],
                             forecast_df["yhat_upper"],
                             color=main_c, alpha=0.15, zorder=2,
                             label="Interval 80%")
            ax1.plot(forecast_df["ds"], forecast_df["yhat_upper"],
                     color=main_c, linewidth=0.8, linestyle="--", alpha=0.5, zorder=3)
            ax1.plot(forecast_df["ds"], forecast_df["yhat_lower"],
                     color=main_c, linewidth=0.8, linestyle="--", alpha=0.5, zorder=3)
            ax1.plot(forecast_df["ds"], forecast_df["yhat"],
                     color=main_c, linewidth=2.5, label="Forecast",
                     marker="o", markersize=4, zorder=5)

        # Garis batas data terakhir
        if not df_hist.empty and not forecast_df.empty:
            last_dt = df_hist["ds"].max()
            ax1.axvline(x=last_dt, color="#F59E0B", linewidth=2, linestyle="--", zorder=6)
            ylim = ax1.get_ylim()
            ax1.text(last_dt, ylim[1], "  Data Terakhir",
                     color="#F59E0B", fontsize=8.5, va="top", ha="left", fontweight="bold")

        # Format axes
        fmt_rp = mticker.FuncFormatter(lambda x, _: f"Rp {x:,.0f}")
        ax1.yaxis.set_major_formatter(fmt_rp)
        ax2.yaxis.set_major_formatter(fmt_rp)
        ax1.set_ylabel("Harga (Rp)", fontsize=10, color="#64748B")
        ax1.tick_params(axis="y", labelsize=9)
        ax2.tick_params(axis="both", labelsize=8)
        plt.setp(ax1.get_xticklabels(), visible=False)
        ax2.xaxis.set_major_formatter(mdates.DateFormatter("%b %Y"))
        ax2.xaxis.set_major_locator(mdates.MonthLocator(interval=3))
        plt.setp(ax2.xaxis.get_majorticklabels(), rotation=30, ha="right", fontsize=8)

        ax1.legend(loc="upper left", fontsize=9, framealpha=0.9,
                   edgecolor="#E2E8F0", facecolor="white")

        # Title
        mape   = metrics.get("mape", 0)
        dir_ac = metrics.get("dir_acc", 0)
        ti = "+" if trend == "increasing" else "-" if trend == "decreasing" else "~"
        fig.suptitle(commodity_name, fontsize=16, fontweight="bold",
                     x=0.02, ha="left", color="#0F172A")
        ax1.set_title(
            f"MAPE: {mape:.2f}%  |  Dir Accuracy: {dir_ac:.1f}%  |  Trend: {ti} {trend}  |  Interval: 80%",
            fontsize=9, color="#64748B", loc="left", pad=6
        )

        plt.tight_layout(rect=[0, 0, 1, 0.96])
        fig.savefig(str(output_path), dpi=150, bbox_inches="tight",
                    facecolor="white", edgecolor="none")
        plt.close(fig)
        return True
    except Exception as e:
        console.print(f"  [yellow]  PNG gagal untuk {commodity_name}: {e}[/yellow]")
        return False


# ----------------------------------------------------------------
# HTML REPORT GENERATOR
# ----------------------------------------------------------------

def generate_html_report(results, output_path, csv_filename):
    timestamp    = datetime.now().strftime("%d %B %Y, %H:%M:%S")
    chart_divs   = []
    summary_rows = []

    for i, res in enumerate(results):
        name    = res["commodity_name"]
        metrics = res["metrics"]
        fig     = res["figure"]
        color   = res["color"]
        trend   = res["trend"]

        chart_html = pio.to_html(fig, include_plotlyjs=False, full_html=False, div_id=f"chart_{i}")

        mape    = metrics.get("mape",     0)
        smape   = metrics.get("smape",    0)
        rmse    = metrics.get("rmse",     0)
        mae     = metrics.get("mae",      0)
        dir_acc = metrics.get("dir_acc",  0)
        cov     = metrics.get("coverage", 0)

        mape_c  = "#10B981" if mape < 8 else "#F59E0B" if mape < 15 else "#EF4444"
        trend_i = "+" if trend == "increasing" else "-" if trend == "decreasing" else "~"
        trend_c = "#10B981" if trend == "increasing" else "#EF4444" if trend == "decreasing" else "#64748B"

        chart_divs.append(f"""
        <section class="comm-section" id="section_{i}">
          <div class="sec-header" style="border-left:5px solid {color}">
            <div class="sec-title">
              <span class="dot" style="background:{color}"></span>{name}
            </div>
            <div class="sec-meta">
              {res['n_hist']} data historis &nbsp;&middot;&nbsp;
              {res['n_pred']} titik forecast &nbsp;&middot;&nbsp;
              Data terakhir: {res['last_date']} &nbsp;&middot;&nbsp;
              Forecast: {res['first_forecast']} &rarr; {res['last_forecast']}
            </div>
          </div>
          <div class="metrics-grid">
            <div class="mc"><div class="ml">MAPE (CV)</div>
              <div class="mv" style="color:{mape_c}">{mape:.4f}%</div></div>
            <div class="mc"><div class="ml">SMAPE</div>
              <div class="mv">{smape:.4f}%</div></div>
            <div class="mc"><div class="ml">RMSE</div>
              <div class="mv">Rp {rmse:,.0f}</div></div>
            <div class="mc"><div class="ml">MAE</div>
              <div class="mv">Rp {mae:,.0f}</div></div>
            <div class="mc"><div class="ml">Directional Acc</div>
              <div class="mv">{dir_acc:.1f}%</div></div>
            <div class="mc"><div class="ml">Coverage 80%</div>
              <div class="mv">{cov*100:.1f}%</div></div>
            <div class="mc"><div class="ml">Trend</div>
              <div class="mv" style="color:{trend_c}">{trend_i} {trend}</div></div>
            <div class="mc"><div class="ml">Hyperparams</div>
              <div class="mv" style="font-size:11px">cp=0.1 cr=0.85 iw=0.80</div></div>
          </div>
          <div class="chart-wrap">{chart_html}</div>
        </section>""")

        summary_rows.append(f"""
        <tr>
          <td><span class="dot-sm" style="background:{color}"></span>{name}</td>
          <td style="color:{mape_c};font-weight:600">{mape:.4f}%</td>
          <td>{smape:.4f}%</td>
          <td>Rp {rmse:,.0f}</td>
          <td>Rp {mae:,.0f}</td>
          <td>{dir_acc:.1f}%</td>
          <td>{cov*100:.1f}%</td>
          <td style="color:{trend_c};font-weight:600">{trend_i} {trend}</td>
          <td>{res['n_hist']}</td>
          <td>{res['n_pred']}</td>
        </tr>""")

    nav_links = "".join(
        f'<a href="#section_{i}" class="nav-link">'
        f'<span class="dot-sm" style="background:{r["color"]}"></span>{r["commodity_name"]}</a>'
        for i, r in enumerate(results)
    )

    n_total  = len(results)
    avg_mape = sum(r["metrics"].get("mape",0) for r in results) / max(n_total,1)
    avg_mc   = "#10B981" if avg_mape < 8 else "#F59E0B" if avg_mape < 15 else "#EF4444"

    html = f"""<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Forecast Report - {csv_filename}</title>
<script src="https://cdn.plot.ly/plotly-2.27.0.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{{box-sizing:border-box;margin:0;padding:0}}
:root{{--bg:#F1F5F9;--surface:#FFFFFF;--border:#E2E8F0;--text:#0F172A;--muted:#64748B;--accent:#3B82F6;--sw:240px}}
body{{font-family:'Plus Jakarta Sans',system-ui,sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}}
.sidebar{{position:fixed;top:0;left:0;width:var(--sw);height:100vh;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow-y:auto;z-index:100}}
.sb-brand{{padding:18px 16px 14px;border-bottom:1px solid var(--border)}}
.sb-brand h2{{font-size:13px;font-weight:800;color:var(--accent)}}
.sb-brand p{{font-size:11px;color:var(--muted);margin-top:2px;word-break:break-all}}
.nav-section{{padding:10px 8px;flex:1}}
.nav-sec-title{{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;padding:0 8px;margin-bottom:6px}}
.nav-link{{display:flex;align-items:center;gap:7px;padding:6px 8px;border-radius:7px;text-decoration:none;color:var(--text);font-size:12px;font-weight:500;transition:background .15s;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}}
.nav-link:hover{{background:#F1F5F9}}
.main{{margin-left:var(--sw);flex:1}}
.topbar{{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}}
.topbar h1{{font-size:17px;font-weight:800;letter-spacing:-.5px}}
.topbar-meta{{font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace}}
.content{{padding:24px 28px;max-width:1400px}}
.strip{{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}}
.strip-card{{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 18px}}
.strip-label{{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}}
.strip-value{{font-size:22px;font-weight:800;letter-spacing:-1px}}
.strip-sub{{font-size:11px;color:var(--muted);margin-top:2px}}
.tbl-wrap{{background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:28px}}
.tbl-title{{padding:14px 18px 10px;font-size:13px;font-weight:700;border-bottom:1px solid var(--border)}}
table{{width:100%;border-collapse:collapse;font-size:12px}}
thead tr{{background:#F8FAFC}}
th{{text-align:left;padding:9px 13px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}}
td{{padding:9px 13px;border-bottom:1px solid #F1F5F9;font-family:'JetBrains Mono',monospace;font-size:11.5px}}
tbody tr:last-child td{{border-bottom:none}}
tbody tr:hover{{background:#FAFCFF}}
.comm-section{{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:24px}}
.sec-header{{padding:16px 22px;background:#F8FAFC;border-bottom:1px solid var(--border)}}
.sec-title{{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:700;letter-spacing:-.3px}}
.sec-meta{{font-size:11px;color:var(--muted);margin-top:3px;font-family:'JetBrains Mono',monospace}}
.metrics-grid{{display:grid;grid-template-columns:repeat(8,1fr);border-bottom:1px solid var(--border)}}
.mc{{padding:12px 14px;border-right:1px solid var(--border);text-align:center}}
.mc:last-child{{border-right:none}}
.ml{{font-size:9.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}}
.mv{{font-size:12.5px;font-weight:700;font-family:'JetBrains Mono',monospace}}
.chart-wrap{{padding:14px}}
.dot{{width:11px;height:11px;border-radius:50%;display:inline-block;flex-shrink:0}}
.dot-sm{{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0;margin-right:2px}}
.footer{{margin-top:28px;padding:18px 0;border-top:1px solid var(--border);text-align:center;font-size:11px;color:var(--muted)}}
.footer code{{background:#F1F5F9;padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',monospace}}
@media(max-width:1100px){{.metrics-grid{{grid-template-columns:repeat(4,1fr)}}}}
@media(max-width:700px){{.sidebar{{display:none}}.main{{margin-left:0}}.strip{{grid-template-columns:repeat(2,1fr)}}}}
</style>
</head>
<body>
<nav class="sidebar">
  <div class="sb-brand">
    <h2>Forecast Report</h2>
    <p>{csv_filename}</p>
  </div>
  <div class="nav-section">
    <div class="nav-sec-title">Komoditas</div>
    {nav_links}
  </div>
</nav>
<div class="main">
  <div class="topbar">
    <h1>Commodity Price Forecast</h1>
    <span class="topbar-meta">Generated: {timestamp} &nbsp;&middot;&nbsp; Prophet v10.2 compatible</span>
  </div>
  <div class="content">
    <div class="strip">
      <div class="strip-card">
        <div class="strip-label">Total Komoditas</div>
        <div class="strip-value">{n_total}</div>
        <div class="strip-sub">dari {csv_filename}</div>
      </div>
      <div class="strip-card">
        <div class="strip-label">Avg MAPE (CV)</div>
        <div class="strip-value" style="color:{avg_mc}">{avg_mape:.2f}%</div>
        <div class="strip-sub">Walk-forward 80/20</div>
      </div>
      <div class="strip-card">
        <div class="strip-label">Model</div>
        <div class="strip-value" style="font-size:15px">Prophet</div>
        <div class="strip-sub">cp=0.1 &middot; cr=0.85 &middot; iw=0.80</div>
      </div>
      <div class="strip-card">
        <div class="strip-label">Dijalankan</div>
        <div class="strip-value" style="font-size:14px">{datetime.now().strftime('%H:%M')}</div>
        <div class="strip-sub">{datetime.now().strftime('%d %b %Y')}</div>
      </div>
    </div>
    <div class="tbl-wrap">
      <div class="tbl-title">Ringkasan Semua Komoditas</div>
      <table>
        <thead><tr>
          <th>Komoditas</th><th>MAPE</th><th>SMAPE</th><th>RMSE</th>
          <th>MAE</th><th>Dir Acc</th><th>Coverage</th><th>Trend</th>
          <th>Data</th><th>Forecast</th>
        </tr></thead>
        <tbody>{"".join(summary_rows)}</tbody>
      </table>
    </div>
    {"".join(chart_divs)}
    <div class="footer">
      Report dibuat oleh <code>run_forecast.py</code> &nbsp;&middot;&nbsp;
      Hyperparameter identik dengan <code>prophet_forecasting.py v10.2</code> &nbsp;&middot;&nbsp;
      {timestamp}
    </div>
  </div>
</div>
</body>
</html>"""

    output_path.write_text(html, encoding="utf-8")
    return output_path


# ----------------------------------------------------------------
# TERMINAL DISPLAY
# ----------------------------------------------------------------

def print_terminal_result(name, res, color):
    m    = res["metrics"]
    trend= res["trend"]
    fdf  = res["forecast_df"]

    mape   = m.get("mape",    0)
    smape  = m.get("smape",   0)
    rmse   = m.get("rmse",    0)
    mae    = m.get("mae",     0)
    dir_ac = m.get("dir_acc", 0)
    cov    = m.get("coverage",0)

    mc = "green" if mape < 8 else "yellow" if mape < 15 else "red"
    tc = "green" if trend == "increasing" else "red" if trend == "decreasing" else "yellow"
    ti = "+" if trend == "increasing" else "-" if trend == "decreasing" else "~"

    console.print(Rule(f"[bold]{name}[/bold]", style="dim"))

    tbl = Table(show_header=True, header_style="bold dim", border_style="dim",
                padding=(0,1), box=None)
    tbl.add_column("Metrik", style="cyan", width=20)
    tbl.add_column("Nilai",  width=16, justify="right")
    tbl.add_column("Metrik", style="cyan", width=20)
    tbl.add_column("Nilai",  width=16, justify="right")

    tbl.add_row("MAPE (CV)",       f"[{mc}]{mape:.4f}%[/{mc}]",   "SMAPE",       f"{smape:.4f}%")
    tbl.add_row("RMSE",            f"Rp {rmse:,.0f}",              "MAE",         f"Rp {mae:,.0f}")
    tbl.add_row("Directional Acc", f"{dir_ac:.1f}%",               "Coverage 80%",f"{cov*100:.1f}%")
    tbl.add_row("Trend",           f"[{tc}]{ti} {trend}[/{tc}]",   "Titik Forecast", str(len(fdf)))
    console.print(tbl)

    if not fdf.empty:
        console.print("\n  [bold]Prediksi 8 minggu pertama:[/bold]")
        pt = Table(show_header=True, header_style="bold dim", border_style="dim",
                   padding=(0,1), box=None)
        pt.add_column("Tanggal",  width=12)
        pt.add_column("Prediksi", width=18, justify="right")
        pt.add_column("Lower",    width=18, justify="right")
        pt.add_column("Upper",    width=18, justify="right")
        for _, row in fdf.head(8).iterrows():
            pt.add_row(
                str(row["ds"].date()),
                f"Rp {row['yhat']:>12,.0f}",
                f"Rp {row['yhat_lower']:>12,.0f}",
                f"Rp {row['yhat_upper']:>12,.0f}",
            )
        console.print(pt)
    console.print()


# ----------------------------------------------------------------
# MAIN
# ----------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Standalone Prophet forecast dari CSV - tanpa Flask API"
    )
    parser.add_argument("--file",    "-f", type=str, default=None,
                        help="Path ke file CSV (default: cari otomatis di folder data/)")
    parser.add_argument("--periods", "-p", type=int, default=12,
                        help="Jumlah minggu ke depan untuk forecast (default: 12)")
    parser.add_argument("--list",    "-l", action="store_true",
                        help="Hanya tampilkan daftar komoditas, tanpa forecast")
    parser.add_argument("--open",    "-o", action="store_true",
                        help="Buka HTML report di browser setelah selesai")
    parser.add_argument("--no-png",        action="store_true",
                        help="Skip simpan PNG (lebih cepat)")
    args = parser.parse_args()

    console.print()
    console.print(Panel.fit(
        "[bold blue]  Commodity Price Forecast[/bold blue]\n"
        "[dim]Standalone  |  Prophet v10.2 compatible  |  No Flask API[/dim]",
        border_style="blue",
    ))

   # Path langsung ke file di folder yang sama dengan script
    csv_path = Path(__file__).parent / "data_forecasting_transformed.csv"

    if not csv_path.exists():
        console.print(f"[red]File tidak ditemukan: {csv_path.name}[/red]")
        console.print(f"[yellow]Pastikan file berada di: {csv_path.absolute()}[/yellow]")
        sys.exit(1)

    console.print(f"\n  Membaca: [bold]{csv_path.name}[/bold]")
    df_raw = load_csv(csv_path)

    # Lanjut ke proses ekstraksi komoditas
    commodities = sorted(df_raw["nama_komoditas"].unique().tolist())
    n_comm      = len(commodities)
    console.print(f"   {len(df_raw):,} baris | [bold]{n_comm}[/bold] komoditas")

    # Mode --list
    if args.list:
        console.print()
        tbl = Table(title="Daftar Komoditas", header_style="bold blue", border_style="dim")
        tbl.add_column("No",          width=4,  justify="right")
        tbl.add_column("Komoditas",   width=45)
        tbl.add_column("Jumlah Data", width=12, justify="right")
        tbl.add_column("Tgl Pertama", width=13)
        tbl.add_column("Tgl Terakhir",width=13)
        for i, name in enumerate(commodities, 1):
            sub = df_raw[df_raw["nama_komoditas"] == name]
            tbl.add_row(str(i), name, f"{len(sub):,}",
                        str(sub["tanggal"].min().date()),
                        str(sub["tanggal"].max().date()))
        console.print(tbl)
        return

    # Forecast
    console.print(f"\n  Mulai forecast [bold]{n_comm}[/bold] komoditas"
                  f" | [bold]{args.periods}[/bold] minggu ke depan\n")

    all_results = []

    with Progress(
        SpinnerColumn(),
        TextColumn("[progress.description]{task.description}"),
        BarColumn(),
        TextColumn("[bold]{task.completed}/{task.total}"),
        TimeElapsedColumn(),
        console=console,
    ) as progress:
        task = progress.add_task("Forecasting...", total=n_comm)

        for i, name in enumerate(commodities):
            color = COMMODITY_COLORS[i % len(COMMODITY_COLORS)]
            progress.update(task, description=f"[cyan]{name[:38]}[/cyan]")

            sub_df = df_raw[df_raw["nama_komoditas"] == name].copy()
            df_p   = prepare_prophet_df(sub_df)

            if len(df_p) < 10:
                console.print(f"  [yellow]  {name}: data terlalu sedikit ({len(df_p)} minggu), skip[/yellow]")
                progress.advance(task)
                continue

            try:
                res = run_single_forecast(df_p, periods=args.periods)
            except Exception as e:
                console.print(f"  [red]  {name}: error - {e}[/red]")
                progress.advance(task)
                continue

            fig = build_plotly_figure(
                commodity_name = name,
                df_hist        = df_p,
                fitted_df      = res["fitted_df"],
                forecast_df    = res["forecast_df"],
                metrics        = res["metrics"],
                color          = color,
            )

            fdf      = res["forecast_df"]
            first_fc = str(fdf["ds"].min().date()) if not fdf.empty else "-"
            last_fc  = str(fdf["ds"].max().date()) if not fdf.empty else "-"

            entry = {
                "commodity_name": name,
                "metrics":        res["metrics"],
                "trend":          res["trend"],
                "figure":         fig,
                "color":          color,
                "n_hist":         len(df_p),
                "n_pred":         len(fdf),
                "last_date":      str(res["last_date"].date()),
                "first_forecast": first_fc,
                "last_forecast":  last_fc,
                "forecast_df":    fdf,
                "fitted_df":      res["fitted_df"],
            }
            all_results.append(entry)

            # Simpan PNG via matplotlib
            if not args.no_png:
                safe  = name.replace("/","_").replace(" ","_").replace("(","").replace(")","")
                ppath = CHARTS_DIR / f"{safe}.png"
                save_png_matplotlib(
                    name, df_p, res["fitted_df"], fdf,
                    res["metrics"], res["trend"], color, ppath
                )

            progress.advance(task)

    if not all_results:
        console.print("[red]Tidak ada komoditas yang berhasil diforecast.[/red]")
        sys.exit(1)

    # Terminal output
    console.print()
    for res in all_results:
        print_terminal_result(res["commodity_name"], res, res["color"])

    # Ringkasan terminal
    console.print(Rule("[bold]Ringkasan Semua Komoditas[/bold]", style="dim"))
    stbl = Table(header_style="bold cyan", border_style="dim", padding=(0,1))
    stbl.add_column("Komoditas",  width=40)
    stbl.add_column("MAPE",       width=9,  justify="right")
    stbl.add_column("Dir Acc",    width=9,  justify="right")
    stbl.add_column("Coverage",   width=9,  justify="right")
    stbl.add_column("Trend",      width=12)
    stbl.add_column("Forecast",   width=26)
    for res in all_results:
        m   = res["metrics"]
        mape= m.get("mape",0)
        mc  = "green" if mape<8 else "yellow" if mape<15 else "red"
        tc  = "green" if res["trend"]=="increasing" else "red" if res["trend"]=="decreasing" else "yellow"
        ti  = "+" if res["trend"]=="increasing" else "-" if res["trend"]=="decreasing" else "~"
        stbl.add_row(
            res["commodity_name"],
            f"[{mc}]{mape:.2f}%[/{mc}]",
            f"{m.get('dir_acc',0):.1f}%",
            f"{m.get('coverage',0)*100:.1f}%",
            f"[{tc}]{ti} {res['trend']}[/{tc}]",
            f"{res['first_forecast']} -> {res['last_forecast']}",
        )
    console.print(stbl)

    # Generate HTML
    console.print()
    ts        = datetime.now().strftime("%Y%m%d_%H%M%S")
    html_path = REPORTS_DIR / f"forecast_report_{ts}.html"
    with console.status("[bold green]Generating HTML report..."):
        generate_html_report(all_results, html_path, csv_path.name)

    # Summary output
    console.print(f"\n  [bold green]Selesai![/bold green]")
    console.print(f"\n   HTML Report : [bold]{html_path}[/bold]")
    if not args.no_png:
        png_files = list(CHARTS_DIR.glob("*.png"))
        console.print(f"   PNG Charts  : [bold]{len(png_files)} file[/bold] di [bold]{CHARTS_DIR}/[/bold]")
    console.print()
    console.print("   Buka report di browser:")
    console.print(f"     Mac/Linux : [bold cyan]open {html_path}[/bold cyan]")
    console.print(f"     Windows   : [bold cyan]start {html_path}[/bold cyan]")
    console.print()

    if args.open:
        webbrowser.open(html_path.as_uri())


if __name__ == "__main__":
    main()