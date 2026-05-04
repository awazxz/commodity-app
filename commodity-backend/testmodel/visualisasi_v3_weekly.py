"""
=============================================================
  VISUALISASI — Hybrid Forecaster v3 Komoditas (Mingguan)
  Disesuaikan untuk data mingguan, multi-komoditas, inflasi/deflasi

  Chart yang dihasilkan:
    chart1_overview.png        — Data historis + forecast mingguan
    chart2_zoom_1y.png         — Zoom 1 tahun terakhir + sinyal naik/turun
    chart3_diagnostics.png     — CV metrics + feature importance
    chart4_inflation.png       — Analisis inflasi/deflasi (output utama)
=============================================================
"""

import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
import matplotlib.gridspec as gridspec
import matplotlib.ticker as mticker
from matplotlib.ticker import FuncFormatter
from matplotlib.colors import LinearSegmentedColormap


# ─────────────────────────────────────────────────────────────
#  HELPER
# ─────────────────────────────────────────────────────────────

def fmt_idr(x, pos=None):
    """Format harga dalam Rupiah."""
    if abs(x) >= 1_000_000:
        return f"Rp{x/1_000_000:.1f}jt"
    elif abs(x) >= 1_000:
        return f"Rp{x:,.0f}"
    return f"Rp{x:.0f}"

idr_fmt = FuncFormatter(fmt_idr)


def fmt_pct(x, pos=None):
    return f"{x:+.1f}%"

pct_fmt = FuncFormatter(fmt_pct)


def get_colors():
    return {
        "actual"      : "#1a1a2e",
        "prophet"     : "#e94560",
        "lgbm"        : "#0f3460",
        "kalman"      : "#553d8c",
        "ensemble"    : "#f5a623",
        "ensemble_v2" : "#27ae60",
        "band"        : "#27ae60",
        "grid"        : "#e8e8e8",
        "bg"          : "#fafafa",
        "inflasi_t"   : "#e74c3c",   # inflasi tinggi
        "inflasi_m"   : "#f39c12",   # inflasi moderat
        "deflasi"     : "#2980b9",   # deflasi
        "stabil"      : "#27ae60",   # stabil
    }


def status_to_color(status: str, C: dict) -> str:
    s = str(status).upper()
    if "INFLASI TINGGI" in s:
        return C["inflasi_t"]
    elif "INFLASI" in s:
        return C["inflasi_m"]
    elif "DEFLASI" in s:
        return C["deflasi"]
    else:
        return C["stabil"]


def get_commodity_name(komoditas_id, results):
    return results.get("komoditas_name", f"Komoditas-{komoditas_id}")


# ─────────────────────────────────────────────────────────────
#  CHART 1 — Overview lengkap (data mingguan)
# ─────────────────────────────────────────────────────────────

def chart1_overview(df, forecast_df, prophet_insample, symbol, C, save_path="chart1_overview.png"):
    fig, axes = plt.subplots(3, 1, figsize=(18, 14),
                             gridspec_kw={"height_ratios": [3, 1, 1]})
    fig.patch.set_facecolor(C["bg"])
    fig.suptitle(
        f"Hybrid Forecaster v3 — {symbol} (Mingguan)\n"
        "Prophet + Direct LGBM + Kalman + Directional Classifier",
        fontsize=14, fontweight="bold", y=0.99
    )

    # ── Panel 1: Harga historis + forecast ──
    ax = axes[0]
    ax.set_facecolor(C["bg"])
    ax.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    ax.plot(df["ds"], df["y"],
            color=C["actual"], linewidth=1.2, label="Aktual (Mingguan)", zorder=5)

    if len(prophet_insample) == len(df):
        ax.plot(df["ds"], prophet_insample,
                color=C["prophet"], linewidth=0.8, alpha=0.4,
                linestyle="--", label="Prophet in-sample")

    ax.plot(forecast_df["ds"], forecast_df["ensemble_v2"],
            color=C["ensemble_v2"], linewidth=2.5,
            label="Ensemble+Dir (v2)", zorder=8)
    ax.plot(forecast_df["ds"], forecast_df["prophet"],
            color=C["prophet"], linewidth=1.2, alpha=0.7,
            linestyle=":", label="Prophet")
    ax.plot(forecast_df["ds"], forecast_df["lgbm_direct"],
            color=C["lgbm"], linewidth=1.2, alpha=0.7,
            linestyle=":", label="LGBM Direct")
    ax.plot(forecast_df["ds"], forecast_df["kalman"],
            color=C["kalman"], linewidth=1.2, alpha=0.7,
            linestyle=":", label="Kalman")
    ax.plot(forecast_df["ds"], forecast_df["ensemble"],
            color=C["ensemble"], linewidth=1.5, alpha=0.8,
            linestyle="--", label="Ensemble v1")
    ax.fill_between(forecast_df["ds"],
                    forecast_df["lower_95"], forecast_df["upper_95"],
                    color=C["band"], alpha=0.15, label="CI 95%")

    last_date     = df["ds"].iloc[-1]
    last_price    = df["y"].iloc[-1]
    last_fc_price = forecast_df["ensemble_v2"].iloc[-1]
    pct_chg       = (last_fc_price - last_price) / last_price * 100

    ax.axvline(last_date, color="gray", linewidth=1.5,
               linestyle="--", alpha=0.8, label="Batas Forecast")
    ax.annotate(f"Last: {fmt_idr(last_price)}",
                xy=(last_date, last_price),
                xytext=(15, 10), textcoords="offset points",
                fontsize=8, color=C["actual"],
                arrowprops=dict(arrowstyle="->", color=C["actual"], lw=1))
    ax.annotate(f"Forecast: {fmt_idr(last_fc_price)} ({pct_chg:+.1f}%)",
                xy=(forecast_df["ds"].iloc[-1], last_fc_price),
                xytext=(-100, 15), textcoords="offset points",
                fontsize=8, color=C["ensemble_v2"],
                arrowprops=dict(arrowstyle="->", color=C["ensemble_v2"], lw=1))

    ax.yaxis.set_major_formatter(idr_fmt)
    ax.set_ylabel(f"Harga {symbol}", fontsize=11)
    ax.set_title(f"Data Historis (Mingguan) + Forecast {len(forecast_df)} Minggu", fontsize=12)
    ax.legend(loc="upper left", fontsize=8, ncol=4, framealpha=0.9)

    # ── Panel 2: Return mingguan ──
    ax2 = axes[1]
    ax2.set_facecolor(C["bg"])
    ax2.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)
    weekly_ret  = df["y"].pct_change() * 100
    colors_ret  = ["#27ae60" if r > 0 else "#e74c3c" for r in weekly_ret.fillna(0)]
    ax2.bar(df["ds"], weekly_ret.fillna(0), color=colors_ret, width=5, alpha=0.7)
    ax2.axhline(0, color="gray", linewidth=0.8)
    ax2.axvline(last_date, color="gray", linewidth=1.5, linestyle="--", alpha=0.8)

    fc_ret    = np.concatenate([[0], np.diff(forecast_df["ensemble_v2"].values) /
                                (forecast_df["ensemble_v2"].values[:-1] + 1e-9) * 100])
    colors_fc = ["#27ae60" if r > 0 else "#e74c3c" for r in fc_ret]
    ax2.bar(forecast_df["ds"], fc_ret, color=colors_fc, width=5, alpha=0.4)
    ax2.set_ylabel("Return Mingguan (%)", fontsize=10)
    ax2.set_title("Return Mingguan — Historis + Forecast", fontsize=11)

    # ── Panel 3: Moving average (dalam minggu) ──
    ax3 = axes[2]
    ax3.set_facecolor(C["bg"])
    ax3.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)
    ax3.plot(df["ds"], df["y"].rolling(52).mean(),
             color=C["prophet"], linewidth=1.8, label="MA 52w (1thn)")
    ax3.plot(df["ds"], df["y"].rolling(13).mean(),
             color=C["lgbm"], linewidth=1.8, linestyle="--", label="MA 13w (3bln)")
    ax3.plot(df["ds"], df["y"], color=C["actual"], linewidth=0.4, alpha=0.3)
    ax3.axvline(last_date, color="gray", linewidth=1.5, linestyle="--", alpha=0.8)
    ax3.yaxis.set_major_formatter(idr_fmt)
    ax3.set_ylabel(f"Harga {symbol}", fontsize=10)
    ax3.set_title("Moving Average Trend (13w & 52w)", fontsize=11)
    ax3.legend(loc="upper left", fontsize=9)

    plt.tight_layout(rect=[0, 0, 1, 0.97])
    plt.savefig(save_path, dpi=150, bbox_inches="tight", facecolor=C["bg"])
    print(f"  [OK] {save_path} tersimpan")
    return fig


# ─────────────────────────────────────────────────────────────
#  CHART 2 — Zoom 1 tahun terakhir + sinyal naik/turun
# ─────────────────────────────────────────────────────────────

def chart2_zoom(df, forecast_df, prophet_insample, symbol, C, save_path="chart2_zoom_1y.png"):
    fig, ax = plt.subplots(figsize=(16, 7))
    fig.patch.set_facecolor(C["bg"])
    ax.set_facecolor(C["bg"])
    ax.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    # Zoom 1 tahun terakhir untuk data mingguan (52 minggu)
    cutoff_1y = df["ds"].max() - pd.DateOffset(years=1)
    recent_df = df[df["ds"] >= cutoff_1y].copy()
    last_date = df["ds"].iloc[-1]

    ax.plot(recent_df["ds"], recent_df["y"],
            color=C["actual"], linewidth=1.5, label="Aktual", zorder=5)

    if len(prophet_insample) == len(df):
        mask = df["ds"] >= cutoff_1y
        ax.plot(recent_df["ds"], prophet_insample[mask.values],
                color=C["prophet"], linewidth=1.0, alpha=0.6,
                linestyle="--", label="Prophet fit")

    ax.plot(forecast_df["ds"], forecast_df["ensemble_v2"],
            color=C["ensemble_v2"], linewidth=2.5,
            label="Ensemble+Dir Forecast", zorder=8)
    ax.plot(forecast_df["ds"], forecast_df["prophet"],
            color=C["prophet"], linewidth=1.2, alpha=0.7,
            linestyle=":", label="Prophet")
    ax.plot(forecast_df["ds"], forecast_df["lgbm_direct"],
            color=C["lgbm"], linewidth=1.2, alpha=0.7,
            linestyle=":", label="LGBM Direct")
    ax.plot(forecast_df["ds"], forecast_df["kalman"],
            color=C["kalman"], linewidth=1.2, alpha=0.7,
            linestyle=":", label="Kalman")
    ax.fill_between(forecast_df["ds"],
                    forecast_df["lower_95"], forecast_df["upper_95"],
                    color=C["band"], alpha=0.2, label="CI 95%")

    # Sinyal arah — setiap 2 minggu agar tidak padat
    for i, (_, row) in enumerate(forecast_df.iterrows()):
        if i % 2 != 0:
            continue
        if "NAIK" in str(row["direction_signal"]):
            ax.annotate("▲", xy=(row["ds"], row["ensemble_v2"]),
                        xytext=(0, 8), textcoords="offset points",
                        fontsize=8, color="#27ae60", ha="center", fontweight="bold")
        elif "TURUN" in str(row["direction_signal"]):
            ax.annotate("▼", xy=(row["ds"], row["ensemble_v2"]),
                        xytext=(0, -14), textcoords="offset points",
                        fontsize=8, color="#e74c3c", ha="center", fontweight="bold")

    ax.axvline(last_date, color="gray", linewidth=1.5, linestyle="--", alpha=0.8)
    ax.yaxis.set_major_formatter(idr_fmt)
    ax.set_title(
        f"{symbol} — Zoom 1 Tahun Terakhir + Forecast {len(forecast_df)} Minggu\n"
        "▲ = Sinyal NAIK  |  ▼ = Sinyal TURUN  (ditampilkan setiap 2 minggu)",
        fontsize=13, fontweight="bold"
    )
    ax.set_xlabel("Tanggal", fontsize=11)
    ax.set_ylabel(f"Harga {symbol} (Rp)", fontsize=11)
    ax.legend(loc="upper left", fontsize=9, framealpha=0.9)

    plt.tight_layout()
    plt.savefig(save_path, dpi=150, bbox_inches="tight", facecolor=C["bg"])
    print(f"  [OK] {save_path} tersimpan")
    return fig


# ─────────────────────────────────────────────────────────────
#  CHART 3 — CV Diagnostics + Feature Importance
# ─────────────────────────────────────────────────────────────

def chart3_diagnostics(cv_results, weights, forecast_df, comp_fc, fi_df,
                        symbol, C, save_path="chart3_diagnostics.png"):
    fig = plt.figure(figsize=(18, 10))
    fig.patch.set_facecolor(C["bg"])
    gs  = gridspec.GridSpec(2, 3, figure=fig, hspace=0.4, wspace=0.35)

    model_names  = ["prophet", "lgbm", "kalman", "ensemble", "ensemble_v2"]
    labels_nice  = ["Prophet", "LGBM\nDirect", "Kalman", "Ensemble\nv1", "Ensemble\n+Dir"]
    colors_model = [C["prophet"], C["lgbm"], C["kalman"], C["ensemble"], C["ensemble_v2"]]

    avg_mapes = [np.mean(cv_results.get(m, {}).get("mape", [0])) for m in model_names]
    std_mapes = [np.std(cv_results.get(m, {}).get("mape",  [0])) for m in model_names]
    avg_daccs = [np.mean(cv_results.get(m, {}).get("dacc", [50])) for m in model_names]

    # ── Bar MAPE ──
    ax3a = fig.add_subplot(gs[0, 0])
    ax3a.set_facecolor(C["bg"])
    ax3a.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)
    bars = ax3a.bar(labels_nice, avg_mapes, color=colors_model,
                    alpha=0.85, edgecolor="white", linewidth=1.2)
    ax3a.errorbar(labels_nice, avg_mapes, yerr=std_mapes,
                  fmt="none", color="black", capsize=5, linewidth=1.5)
    for bar, val in zip(bars, avg_mapes):
        ax3a.text(bar.get_x() + bar.get_width() / 2,
                  bar.get_height() + max(avg_mapes) * 0.01,
                  f"{val:.2f}%", ha="center", va="bottom",
                  fontsize=8, fontweight="bold")
    ax3a.set_title("CV MAPE per Model — Data Mingguan", fontsize=11)
    ax3a.set_ylabel("MAPE (%)", fontsize=10)
    ax3a.set_ylim(0, max(avg_mapes) * 1.35 if max(avg_mapes) > 0 else 10)

    # ── Bar Directional Accuracy ──
    ax3b = fig.add_subplot(gs[0, 1])
    ax3b.set_facecolor(C["bg"])
    ax3b.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)
    bars2 = ax3b.bar(labels_nice, avg_daccs, color=colors_model,
                     alpha=0.85, edgecolor="white", linewidth=1.2)
    ax3b.axhline(50, color="gray",    linewidth=1, linestyle="--", label="Random 50%")
    ax3b.axhline(60, color="#27ae60", linewidth=1, linestyle="--", alpha=0.7, label="Target 60%")
    for bar, val in zip(bars2, avg_daccs):
        ax3b.text(bar.get_x() + bar.get_width() / 2, bar.get_height() + 0.5,
                  f"{val:.1f}%", ha="center", va="bottom",
                  fontsize=8, fontweight="bold")
    ax3b.set_title("Directional Accuracy per Model", fontsize=11)
    ax3b.set_ylabel("Dir. Accuracy (%)", fontsize=10)
    ax3b.set_ylim(0, 100)
    ax3b.legend(fontsize=8)

    # ── Pie bobot ensemble ──
    ax3c = fig.add_subplot(gs[0, 2])
    ax3c.set_facecolor(C["bg"])
    w_labels  = list(weights.keys())
    w_values  = list(weights.values())
    w_colors  = [C["prophet"], C["lgbm"], C["kalman"]]
    w_explode = [0.05 if v == max(w_values) else 0 for v in w_values]
    _, _, autotexts = ax3c.pie(
        w_values,
        labels     = [l.capitalize() for l in w_labels],
        colors     = w_colors[:len(w_labels)],
        explode    = w_explode,
        autopct    = "%1.1f%%",
        startangle = 90,
        textprops  = {"fontsize": 10},
    )
    for at in autotexts:
        at.set_fontsize(9)
        at.set_fontweight("bold")
    ax3c.set_title("Bobot Ensemble Dinamis\n(dari CV MAPE inverse)", fontsize=11)

    # ── Line forecast per komponen ──
    ax3d = fig.add_subplot(gs[1, :2])
    ax3d.set_facecolor(C["bg"])
    ax3d.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)
    horizons = list(range(1, len(forecast_df) + 1))
    if "prophet" in comp_fc:
        ax3d.plot(horizons, comp_fc["prophet"], color=C["prophet"],
                  linewidth=1.5, linestyle=":", label="Prophet", alpha=0.8)
    if "lgbm" in comp_fc:
        ax3d.plot(horizons, comp_fc["lgbm"], color=C["lgbm"],
                  linewidth=1.5, linestyle=":", label="LGBM Direct", alpha=0.8)
    if "kalman" in comp_fc:
        ax3d.plot(horizons, comp_fc["kalman"], color=C["kalman"],
                  linewidth=1.5, linestyle=":", label="Kalman", alpha=0.8)
    ax3d.plot(horizons, forecast_df["ensemble"],
              color=C["ensemble"], linewidth=2, linestyle="--",
              label="Ensemble v1", alpha=0.9)
    ax3d.plot(horizons, forecast_df["ensemble_v2"],
              color=C["ensemble_v2"], linewidth=2.5,
              label="Ensemble+Dir (v2)", zorder=8)
    ax3d.fill_between(horizons,
                      forecast_df["lower_95"], forecast_df["upper_95"],
                      color=C["band"], alpha=0.15, label="CI 95%")
    ax3d.yaxis.set_major_formatter(idr_fmt)
    ax3d.set_xlabel("Horizon (minggu ke depan)", fontsize=10)
    ax3d.set_ylabel(f"Harga {symbol} (Rp)", fontsize=10)
    ax3d.set_title(f"Perbandingan Forecast per Komponen ({len(forecast_df)} minggu)", fontsize=11)
    ax3d.legend(loc="best", fontsize=9, ncol=3)

    # ── Feature importance ──
    ax3e = fig.add_subplot(gs[1, 2])
    ax3e.set_facecolor(C["bg"])
    if fi_df is not None and not fi_df.empty:
        top10 = fi_df.head(10)
        y_pos = range(len(top10))
        ax3e.barh(y_pos, top10["importance"],
                  color=C["lgbm"], alpha=0.8, edgecolor="white")
        ax3e.set_yticks(y_pos)
        ax3e.set_yticklabels(top10["feature"], fontsize=8)
        ax3e.invert_yaxis()
        ax3e.set_title("Top 10 Fitur LGBM Direct", fontsize=11)
        ax3e.set_xlabel("Avg Importance", fontsize=9)
        ax3e.grid(True, axis="x", color=C["grid"], linewidth=0.5)
    else:
        ax3e.text(0.5, 0.5, "Feature importance\ntidak tersedia",
                  ha="center", va="center", transform=ax3e.transAxes)
        ax3e.set_title("Top 10 Fitur LGBM Direct", fontsize=11)

    plt.suptitle(f"CV Diagnostics + Komponen Forecast — {symbol} (Mingguan)",
                 fontsize=14, fontweight="bold", y=1.01)
    plt.savefig(save_path, dpi=150, bbox_inches="tight", facecolor=C["bg"])
    print(f"  [OK] {save_path} tersimpan")
    return fig


# ─────────────────────────────────────────────────────────────
#  CHART 4 — Inflasi / Deflasi (output utama baru)
# ─────────────────────────────────────────────────────────────

def chart4_inflation(df, inflation_df, hist_inflation, forecast_df, symbol,
                     C, save_path="chart4_inflation.png"):
    fig = plt.figure(figsize=(20, 14))
    fig.patch.set_facecolor(C["bg"])
    gs  = gridspec.GridSpec(3, 2, figure=fig, hspace=0.45, wspace=0.3)

    # ── Panel A: Harga historis + tren inflasi (MA 4w & 52w) ──
    ax_a = fig.add_subplot(gs[0, :])
    ax_a.set_facecolor(C["bg"])
    ax_a.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    ax_a.plot(df["ds"], df["y"],
              color=C["actual"], linewidth=1.0, alpha=0.6, label="Aktual", zorder=3)
    ax_a.plot(df["ds"], df["y"].rolling(4).mean(),
              color=C["prophet"], linewidth=1.5, label="MA 4w (1bln)", zorder=4)
    ax_a.plot(df["ds"], df["y"].rolling(52).mean(),
              color=C["lgbm"], linewidth=2, linestyle="--", label="MA 52w (1thn)", zorder=4)

    # Forecast
    ax_a.plot(forecast_df["ds"], forecast_df["ensemble_v2"],
              color=C["ensemble_v2"], linewidth=2.5, label="Forecast Ensemble+Dir", zorder=8)
    ax_a.fill_between(forecast_df["ds"],
                      forecast_df["lower_95"], forecast_df["upper_95"],
                      color=C["band"], alpha=0.15, label="CI 95%")

    last_date = df["ds"].iloc[-1]
    ax_a.axvline(last_date, color="gray", linewidth=1.5, linestyle="--", alpha=0.8)

    # Warna background berdasarkan status inflasi historis
    if hist_inflation is not None and "inflation_status" in hist_inflation.columns:
        hi = hist_inflation.dropna(subset=["pct_4w"]).copy()
        for i in range(len(hi) - 1):
            row  = hi.iloc[i]
            row2 = hi.iloc[i + 1]
            col  = status_to_color(row["inflation_status"], C)
            ax_a.axvspan(row["ds"], row2["ds"], alpha=0.04, color=col, linewidth=0)

    ax_a.yaxis.set_major_formatter(idr_fmt)
    ax_a.set_ylabel(f"Harga {symbol} (Rp)", fontsize=11)
    ax_a.set_title(
        f"Historis Harga + Forecast — {symbol}\n"
        "Background: Merah=Inflasi Tinggi, Oranye=Inflasi Moderat, Biru=Deflasi, Hijau=Stabil",
        fontsize=12
    )
    ax_a.legend(loc="upper left", fontsize=9, ncol=5, framealpha=0.9)

    # ── Panel B: YoY historis (perubahan tahunan) ──
    ax_b = fig.add_subplot(gs[1, 0])
    ax_b.set_facecolor(C["bg"])
    ax_b.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    if hist_inflation is not None and "pct_52w" in hist_inflation.columns:
        hi_yoy = hist_inflation.dropna(subset=["pct_52w"])
        bar_colors = [C["inflasi_t"] if v > 4 else
                      C["inflasi_m"] if v > 2 else
                      C["deflasi"]   if v < -2 else
                      C["stabil"] for v in hi_yoy["pct_52w"]]
        ax_b.bar(hi_yoy["ds"], hi_yoy["pct_52w"], color=bar_colors, width=5, alpha=0.8)
        ax_b.axhline(0,  color="black", linewidth=0.8)
        ax_b.axhline(2,  color=C["inflasi_m"], linewidth=1, linestyle="--",
                     alpha=0.7, label="+2% threshold")
        ax_b.axhline(-2, color=C["deflasi"],   linewidth=1, linestyle="--",
                     alpha=0.7, label="-2% threshold")
        ax_b.yaxis.set_major_formatter(pct_fmt)

    ax_b.set_title("Inflasi/Deflasi YoY — Historis (52w)", fontsize=11)
    ax_b.set_ylabel("Perubahan Harga YoY (%)", fontsize=10)
    ax_b.legend(fontsize=8)

    # ── Panel C: MoM historis (perubahan bulanan 4w) ──
    ax_c = fig.add_subplot(gs[1, 1])
    ax_c.set_facecolor(C["bg"])
    ax_c.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    if hist_inflation is not None and "pct_4w" in hist_inflation.columns:
        hi_mom = hist_inflation.dropna(subset=["pct_4w"])
        bar_colors_m = [C["inflasi_t"] if v > 4 else
                        C["inflasi_m"] if v > 2 else
                        C["deflasi"]   if v < -2 else
                        C["stabil"] for v in hi_mom["pct_4w"]]
        ax_c.bar(hi_mom["ds"], hi_mom["pct_4w"], color=bar_colors_m, width=5, alpha=0.8)
        ax_c.axhline(0,  color="black", linewidth=0.8)
        ax_c.axhline(2,  color=C["inflasi_m"], linewidth=1, linestyle="--", alpha=0.7)
        ax_c.axhline(-2, color=C["deflasi"],   linewidth=1, linestyle="--", alpha=0.7)
        ax_c.yaxis.set_major_formatter(pct_fmt)

    ax_c.set_title("Inflasi/Deflasi MoM — Historis (4w)", fontsize=11)
    ax_c.set_ylabel("Perubahan Harga MoM (%)", fontsize=10)

    # ── Panel D: Forecast MoM + YoY (6 bulan ke depan) ──
    ax_d = fig.add_subplot(gs[2, 0])
    ax_d.set_facecolor(C["bg"])
    ax_d.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    if inflation_df is not None and len(inflation_df) > 0:
        x_pos = np.arange(len(inflation_df))
        width = 0.35
        mom_colors = [status_to_color(s, C) for s in inflation_df["inflation_status"]]
        ax_d.bar(x_pos - width/2, inflation_df["pct_4w"].fillna(0),
                 width=width, color=mom_colors, alpha=0.85, label="MoM (4w)")
        ax_d.bar(x_pos + width/2, inflation_df["pct_52w"].fillna(0),
                 width=width, color=[C["lgbm"]] * len(inflation_df),
                 alpha=0.5, label="YoY (52w)")
        ax_d.axhline(0,  color="black", linewidth=0.8)
        ax_d.axhline(2,  color=C["inflasi_m"], linewidth=1, linestyle="--", alpha=0.7)
        ax_d.axhline(-2, color=C["deflasi"],   linewidth=1, linestyle="--", alpha=0.7)
        ax_d.set_xticks(x_pos[::4])
        ax_d.set_xticklabels(
            [str(d.date()) for d in inflation_df["ds"].iloc[::4]],
            rotation=30, ha="right", fontsize=7
        )
        ax_d.yaxis.set_major_formatter(pct_fmt)

    ax_d.set_title("Forecast Inflasi/Deflasi — MoM & YoY (26 minggu)", fontsize=11)
    ax_d.set_ylabel("Perubahan Harga (%)", fontsize=10)
    ax_d.legend(fontsize=8)

    # ── Panel E: Perubahan kumulatif 6 bulan + annualized rate ──
    ax_e = fig.add_subplot(gs[2, 1])
    ax_e.set_facecolor(C["bg"])
    ax_e.grid(True, color=C["grid"], linewidth=0.5, alpha=0.7)

    if inflation_df is not None and len(inflation_df) > 0:
        pct_26w = inflation_df["pct_26w"].values
        ann_rate = inflation_df["annualized_rate"].values
        x_pos   = np.arange(len(inflation_df))

        color_cum = C["inflasi_t"] if pct_26w[-1] > 4 else \
                    C["inflasi_m"] if pct_26w[-1] > 0 else \
                    C["deflasi"]

        ax_e.fill_between(x_pos, pct_26w, 0,
                          color=color_cum, alpha=0.3)
        ax_e.plot(x_pos, pct_26w,
                  color=color_cum, linewidth=2, label="Kumulatif 6 bulan")
        ax_e.plot(x_pos, ann_rate,
                  color=C["lgbm"], linewidth=2, linestyle="--",
                  label="Implied annual rate")
        ax_e.axhline(0, color="black", linewidth=0.8)
        ax_e.axhline(2,  color=C["inflasi_m"], linewidth=1, linestyle=":", alpha=0.6)
        ax_e.axhline(-2, color=C["deflasi"],   linewidth=1, linestyle=":", alpha=0.6)

        # Anotasi nilai akhir
        final_cum = pct_26w[-1]
        final_ann = ann_rate[-1]
        ax_e.annotate(f"Kumulatif: {final_cum:+.2f}%",
                      xy=(len(inflation_df)-1, final_cum),
                      xytext=(-60, 10), textcoords="offset points",
                      fontsize=8, color=color_cum, fontweight="bold",
                      arrowprops=dict(arrowstyle="->", color=color_cum, lw=1))
        ax_e.annotate(f"Annual: {final_ann:+.2f}%/thn",
                      xy=(len(inflation_df)-1, final_ann),
                      xytext=(-80, -15), textcoords="offset points",
                      fontsize=8, color=C["lgbm"], fontweight="bold",
                      arrowprops=dict(arrowstyle="->", color=C["lgbm"], lw=1))

        ax_e.set_xticks(x_pos[::4])
        ax_e.set_xticklabels(
            [str(d.date()) for d in inflation_df["ds"].iloc[::4]],
            rotation=30, ha="right", fontsize=7
        )
        ax_e.yaxis.set_major_formatter(pct_fmt)

    ax_e.set_title("Perubahan Kumulatif & Implied Annual Rate", fontsize=11)
    ax_e.set_ylabel("Inflasi/Deflasi (%)", fontsize=10)
    ax_e.legend(fontsize=8)

    # Legend status warna
    patches = [
        mpatches.Patch(color=C["inflasi_t"], label="Inflasi Tinggi (>4%)"),
        mpatches.Patch(color=C["inflasi_m"], label="Inflasi Moderat (2–4%)"),
        mpatches.Patch(color=C["stabil"],    label="Stabil (-2% s/d 2%)"),
        mpatches.Patch(color=C["deflasi"],   label="Deflasi (<-2%)"),
    ]
    fig.legend(handles=patches, loc="lower center", ncol=4,
               fontsize=9, framealpha=0.9, bbox_to_anchor=(0.5, -0.01))

    plt.suptitle(
        f"Analisis Inflasi/Deflasi — {symbol} (Mingguan)\n"
        "Forecast 26 Minggu ke Depan",
        fontsize=14, fontweight="bold"
    )
    plt.savefig(save_path, dpi=150, bbox_inches="tight", facecolor=C["bg"])
    print(f"  [OK] {save_path} tersimpan")
    return fig


# ─────────────────────────────────────────────────────────────
#  ENTRY POINT
# ─────────────────────────────────────────────────────────────

def run_all_charts(forecaster, output_prefix: str = ""):
    """
    Buat semua chart dari instance HybridForecasterV3Weekly.

    Parameters:
        forecaster     : instance yang sudah .load().run_cv().fit_all().forecast()
        output_prefix  : prefix nama file (kosong = tidak ada prefix)
    """
    df           = forecaster.df
    forecast_df  = forecaster.forecast_df
    inflation_df = forecaster.inflation_df
    hist_inf     = forecaster.hist_inflation
    results      = forecaster.results
    kid          = forecaster.komoditas_id
    symbol       = results.get("komoditas_name", f"Komoditas-{kid}")
    C            = get_colors()

    prophet_insample = results.get("prophet_insample", np.array([]))
    full_prophet_fc  = results.get("prophet_full_forecast", pd.DataFrame())
    cv_results       = results.get("cv_results", {})
    weights          = results.get("weights", forecaster.ensemble.weights)
    comp_fc          = results.get("component_forecasts", {})
    fi_df            = forecaster.get_feature_importance()

    pfx = f"{output_prefix}_" if output_prefix else ""

    print(f"\n  Membuat chart 1/4: Overview mingguan ({symbol})...")
    chart1_overview(df, forecast_df, prophet_insample, symbol, C,
                    save_path=f"{pfx}chart1_overview.png")

    print(f"  Membuat chart 2/4: Zoom 1 tahun terakhir ({symbol})...")
    chart2_zoom(df, forecast_df, prophet_insample, symbol, C,
                save_path=f"{pfx}chart2_zoom_1y.png")

    print(f"  Membuat chart 3/4: CV Diagnostics ({symbol})...")
    chart3_diagnostics(cv_results, weights, forecast_df, comp_fc, fi_df, symbol, C,
                       save_path=f"{pfx}chart3_diagnostics.png")

    print(f"  Membuat chart 4/4: Analisis Inflasi/Deflasi ({symbol})...")
    chart4_inflation(df, inflation_df, hist_inf, forecast_df, symbol, C,
                     save_path=f"{pfx}chart4_inflation.png")

    # ── Ringkasan terminal ──
    last_price    = df["y"].iloc[-1]
    last_fc_price = forecast_df["ensemble_v2"].iloc[-1]
    pct_chg       = (last_fc_price - last_price) / last_price * 100

    print(f"\n{'='*60}")
    print(f"  RINGKASAN — {symbol} (Mingguan)")
    print(f"{'='*60}")
    print(f"  Data        : {df['ds'].min().date()} -> {df['ds'].max().date()}")
    print(f"  N minggu    : {len(df)} ({len(df)/52:.1f} tahun)")
    print(f"  Harga last  : {fmt_idr(last_price)}")
    print(f"  Forecast    : {len(forecast_df)} minggu ke depan")
    print(f"  Forecast end: {fmt_idr(last_fc_price)} ({pct_chg:+.1f}%)")
    print(f"  CI 95%      : [{fmt_idr(forecast_df['lower_95'].iloc[-1])}, "
          f"{fmt_idr(forecast_df['upper_95'].iloc[-1])}]")

    if inflation_df is not None and len(inflation_df) > 0:
        last_inf = inflation_df.iloc[-1]
        print(f"\n  Inflasi/Deflasi 6 Bulan ke Depan:")
        print(f"    Kumulatif 6 bulan  : {last_inf['pct_26w']:+.2f}%")
        print(f"    Implied annual rate: {last_inf['annualized_rate']:+.2f}%/tahun")
        print(f"    Status akhir       : {last_inf['inflation_status']}")

    print(f"\n  CV Directional Accuracy:")
    for m in ["prophet", "lgbm", "ensemble_v2"]:
        daccs = cv_results.get(m, {}).get("dacc", [])
        if daccs:
            label = "Ensemble+Dir" if m == "ensemble_v2" else m.capitalize()
            print(f"    {label:<14}: {np.mean(daccs):.1f}% (+/-{np.std(daccs):.1f}%)")
    print(f"{'='*60}")