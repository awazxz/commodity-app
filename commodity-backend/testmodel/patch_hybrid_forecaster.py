"""
=============================================================
  PATCH untuk hybrid_forecaster_v3_weekly.py
  Jalankan SEKALI untuk memperbaiki 3 bug:

    python patch_hybrid_forecaster.py

  Bug yang diperbaiki:
  1. Segmentation fault / "Model format error" di DirClassifier
     → custom objective _obj didefinisikan di dalam loop (closure bug)
     → FIX: pindahkan _obj ke luar kelas sebagai fungsi global

  2. "Check failed: train_data->num_features() > 0"
     → data setelah dropna() kosong di CV fold kecil
     → FIX: tambah guard len(X) > 0 sebelum fit

  3. Fold 2 selalu error karena min_train terlalu kecil
     → cv_test_size=52 dengan hanya 198 baris: fold 2 train_end=94
        tapi setelah dropna fitur lag_52w hilang semua
     → FIX: naikkan min train dari 60 → 80 + skip jika X kosong
=============================================================
"""

import re
import shutil
import os
from pathlib import Path

TARGET = "hybrid_forecaster_v3_weekly.py"

if not os.path.exists(TARGET):
    print(f"[Error] File {TARGET} tidak ditemukan di folder ini.")
    print("Pastikan script ini dijalankan di folder yang sama dengan hybrid_forecaster_v3_weekly.py")
    exit(1)

# Backup dulu
backup = TARGET + ".bak"
shutil.copy2(TARGET, backup)
print(f"[OK] Backup tersimpan: {backup}")

with open(TARGET, "r", encoding="utf-8") as f:
    src = f.read()

original_src = src

# ══════════════════════════════════════════════════════════════
#  FIX 1: Pindahkan custom objective keluar dari loop
#  Ganti definisi _obj di dalam loop fit() dengan fungsi global
# ══════════════════════════════════════════════════════════════

OLD_DIRECTIONAL_LOSS_USAGE = '''    def fit(self, df, prophet_residuals=None):
        feat = build_features(df, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
            n = min(len(prophet_residuals), len(feat))
            feat.loc[feat.index[-n:], "prophet_residual"] = prophet_residuals[-n:]

        self.feature_cols = self._get_feature_cols(feat)
        feat_clean = feat.dropna()
        self.scaler.fit(feat_clean[self.feature_cols].values)

        print(f"    [DirectLGBM] Training {self.H} model...", end="", flush=True)
        for h in range(1, self.H + 1):
            X, y = self._prepare_Xy(feat, horizon=h)
            X_scaled = self.scaler.transform(X)

            if self.use_directional_loss:
                def _obj(y_true, y_pred):
                    return directional_loss(y_true, y_pred)
                model = lgb.LGBMRegressor(
                    objective        = _obj,
                    n_estimators     = self.hp.get("n_estimators", 400),
                    learning_rate    = self.hp.get("learning_rate", 0.03),
                    max_depth        = self.hp.get("max_depth", 5),
                    num_leaves       = self.hp.get("num_leaves", 31),
                    subsample        = self.hp.get("subsample", 0.8),
                    colsample_bytree = self.hp.get("colsample_bytree", 0.8),
                    reg_alpha        = self.hp.get("reg_alpha", 0.1),
                    reg_lambda       = self.hp.get("reg_lambda", 0.5),
                    random_state     = self.hp.get("random_state", 42),
                    verbose          = -1,
                )
                model.fit(X_scaled, y, callbacks=[lgb.log_evaluation(period=-1)])
            else:
                model = lgb.LGBMRegressor(**self.hp)
                model.fit(X_scaled, y)

            self.models[h] = model
            if h % 5 == 0 or h == self.H:
                print(f" {h}", end="", flush=True)
        print()
        return self'''

NEW_DIRECTIONAL_LOSS_USAGE = '''    def fit(self, df, prophet_residuals=None):
        feat = build_features(df, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
            n = min(len(prophet_residuals), len(feat))
            feat.loc[feat.index[-n:], "prophet_residual"] = prophet_residuals[-n:]

        self.feature_cols = self._get_feature_cols(feat)
        feat_clean = feat.dropna()

        # FIX: guard jika feat_clean kosong
        if len(feat_clean) == 0:
            print(f"    [DirectLGBM] Skip — data tidak cukup setelah dropna()")
            return self

        self.scaler.fit(feat_clean[self.feature_cols].values)

        print(f"    [DirectLGBM] Training {self.H} model...", end="", flush=True)
        for h in range(1, self.H + 1):
            X, y = self._prepare_Xy(feat, horizon=h)

            # FIX: skip jika data terlalu sedikit
            if len(X) < 10:
                continue

            X_scaled = self.scaler.transform(X)

            if self.use_directional_loss:
                # FIX: gunakan fungsi global _lgbm_directional_obj (bukan closure dalam loop)
                model = lgb.LGBMRegressor(
                    objective        = _lgbm_directional_obj,
                    n_estimators     = self.hp.get("n_estimators", 400),
                    learning_rate    = self.hp.get("learning_rate", 0.03),
                    max_depth        = self.hp.get("max_depth", 5),
                    num_leaves       = self.hp.get("num_leaves", 31),
                    subsample        = self.hp.get("subsample", 0.8),
                    colsample_bytree = self.hp.get("colsample_bytree", 0.8),
                    reg_alpha        = self.hp.get("reg_alpha", 0.1),
                    reg_lambda       = self.hp.get("reg_lambda", 0.5),
                    random_state     = self.hp.get("random_state", 42),
                    verbose          = -1,
                )
                model.fit(X_scaled, y, callbacks=[lgb.log_evaluation(period=-1)])
            else:
                model = lgb.LGBMRegressor(**self.hp)
                model.fit(X_scaled, y)

            self.models[h] = model
            if h % 5 == 0 or h == self.H:
                print(f" {h}", end="", flush=True)
        print()
        return self'''

if OLD_DIRECTIONAL_LOSS_USAGE in src:
    src = src.replace(OLD_DIRECTIONAL_LOSS_USAGE, NEW_DIRECTIONAL_LOSS_USAGE)
    print("[OK] Fix 1 applied: custom objective dipindah ke fungsi global")
else:
    print("[WARN] Fix 1: pattern tidak ditemukan, mungkin sudah dipatch atau format berbeda")

# ══════════════════════════════════════════════════════════════
#  FIX 1b: Tambah fungsi global _lgbm_directional_obj
#  tepat sebelum class DirectMultiStepLGBM
# ══════════════════════════════════════════════════════════════

GLOBAL_OBJ_FUNC = '''
# ── Global objective function untuk LightGBM (FIX: hindari closure bug) ──
def _lgbm_directional_obj(y_true, y_pred):
    """Custom asymmetric objective — harus global, bukan closure dalam loop."""
    return directional_loss(y_true, y_pred)

'''

MARKER_BEFORE_CLASS = "class DirectMultiStepLGBM:"

if GLOBAL_OBJ_FUNC.strip() not in src:
    if MARKER_BEFORE_CLASS in src:
        src = src.replace(MARKER_BEFORE_CLASS, GLOBAL_OBJ_FUNC + MARKER_BEFORE_CLASS)
        print("[OK] Fix 1b applied: fungsi _lgbm_directional_obj ditambahkan sebagai global")
    else:
        print("[WARN] Fix 1b: marker class tidak ditemukan")
else:
    print("[OK] Fix 1b: sudah ada, dilewati")

# ══════════════════════════════════════════════════════════════
#  FIX 2: Guard di DirectionalClassifier.fit()
#  Tambah pengecekan len(X) > 0 sebelum fit classifier
# ══════════════════════════════════════════════════════════════

OLD_DIRCLF_FIT = '''        feat_clean        = feat.dropna().copy()
        self.feature_cols = [c for c in feat_clean.columns if c not in {"ds", "y"}]
        self.scaler.fit(feat_clean[self.feature_cols].values)'''

NEW_DIRCLF_FIT = '''        feat_clean        = feat.dropna().copy()
        self.feature_cols = [c for c in feat_clean.columns if c not in {"ds", "y"}]

        # FIX: guard jika data kosong setelah dropna
        if len(feat_clean) == 0 or len(self.feature_cols) == 0:
            print(f"    [DirClassifier] Skip — data tidak cukup setelah dropna()")
            return self

        self.scaler.fit(feat_clean[self.feature_cols].values)'''

if OLD_DIRCLF_FIT in src:
    src = src.replace(OLD_DIRCLF_FIT, NEW_DIRCLF_FIT)
    print("[OK] Fix 2 applied: guard di DirectionalClassifier.fit()")
else:
    print("[WARN] Fix 2: pattern tidak ditemukan")

# ══════════════════════════════════════════════════════════════
#  FIX 3: Naikkan batas minimum training data di rolling_cv
#  dari 60 → 80 agar lag_52w punya data cukup
# ══════════════════════════════════════════════════════════════

OLD_MIN_TRAIN = "            if train_end < 60:   # minimal ~1 tahun data mingguan untuk training\n                print(f\"  [Fold {fold+1}] Skip — data training < 60 minggu\")"
NEW_MIN_TRAIN = "            if train_end < 80:   # minimal ~1.5 tahun agar lag_52w tidak kosong\n                print(f\"  [Fold {fold+1}] Skip — data training < 80 minggu\")"

if OLD_MIN_TRAIN in src:
    src = src.replace(OLD_MIN_TRAIN, NEW_MIN_TRAIN)
    print("[OK] Fix 3 applied: min train_end naik dari 60 → 80")
else:
    print("[WARN] Fix 3: pattern tidak ditemukan, coba cari manual")

# ══════════════════════════════════════════════════════════════
#  FIX 4: Tambah guard di DirClassifier predict_proba
#  jika self.feature_cols belum di-set (karena fit dilewati)
# ══════════════════════════════════════════════════════════════

OLD_PREDICT_PROBA = '''    def predict_proba(self, history, prophet_residuals=None):
        feat = build_features(history, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
        for c in self.feature_cols:
            if c not in feat.columns:
                feat[c] = 0.0
        last_row = feat.iloc[[-1]][self.feature_cols].values
        X_scaled = self.scaler.transform(last_row)
        result   = {}
        for h in range(1, self.H + 1):
            if h not in self.models:
                result[h] = {"up": 0.333, "flat": 0.333, "down": 0.333}
                continue
            proba     = self.models[h].predict_proba(X_scaled)[0]
            result[h] = {"down": float(proba[0]), "flat": float(proba[1]), "up": float(proba[2])}
        return result'''

NEW_PREDICT_PROBA = '''    def predict_proba(self, history, prophet_residuals=None):
        # FIX: jika model tidak di-train (data kurang), return default
        if not self.models or self.feature_cols is None:
            return {h: {"up": 0.333, "flat": 0.333, "down": 0.333}
                    for h in range(1, self.H + 1)}

        feat = build_features(history, self.lags, self.windows)
        if prophet_residuals is not None:
            feat["prophet_residual"] = 0.0
        for c in self.feature_cols:
            if c not in feat.columns:
                feat[c] = 0.0
        last_row = feat.iloc[[-1]][self.feature_cols].values
        X_scaled = self.scaler.transform(last_row)
        result   = {}
        for h in range(1, self.H + 1):
            if h not in self.models:
                result[h] = {"up": 0.333, "flat": 0.333, "down": 0.333}
                continue
            proba     = self.models[h].predict_proba(X_scaled)[0]
            result[h] = {"down": float(proba[0]), "flat": float(proba[1]), "up": float(proba[2])}
        return result'''

if OLD_PREDICT_PROBA in src:
    src = src.replace(OLD_PREDICT_PROBA, NEW_PREDICT_PROBA)
    print("[OK] Fix 4 applied: guard di predict_proba jika model belum di-train")
else:
    print("[WARN] Fix 4: pattern tidak ditemukan")

# ══════════════════════════════════════════════════════════════
#  FIX 5: Guard di DirectMultiStepLGBM.predict()
#  jika self.models kosong
# ══════════════════════════════════════════════════════════════

OLD_LGBM_PREDICT = '''    def predict(self, history, prophet_future=None):
        feat = build_features(history, self.lags, self.windows)
        if prophet_future is not None:
            feat["prophet_residual"] = 0.0
        last_row = feat.iloc[[-1]].copy()
        for c in self.feature_cols:
            if c not in last_row.columns:
                last_row[c] = 0.0
        X        = last_row[self.feature_cols].values
        X_scaled = self.scaler.transform(X)
        preds    = [self.models[h].predict(X_scaled)[0] for h in range(1, self.H + 1)]
        return np.array(preds)'''

NEW_LGBM_PREDICT = '''    def predict(self, history, prophet_future=None):
        # FIX: jika model tidak di-train, return array nol
        if not self.models or self.feature_cols is None:
            last_price = float(history["y"].iloc[-1])
            return np.full(self.H, last_price)

        feat = build_features(history, self.lags, self.windows)
        if prophet_future is not None:
            feat["prophet_residual"] = 0.0
        last_row = feat.iloc[[-1]].copy()
        for c in self.feature_cols:
            if c not in last_row.columns:
                last_row[c] = 0.0
        X        = last_row[self.feature_cols].values
        X_scaled = self.scaler.transform(X)
        preds    = [self.models[h].predict(X_scaled)[0]
                    for h in range(1, self.H + 1) if h in self.models]
        # Pad jika ada horizon yang dilewati
        if len(preds) < self.H:
            last_val = preds[-1] if preds else float(history["y"].iloc[-1])
            preds = preds + [last_val] * (self.H - len(preds))
        return np.array(preds)'''

if OLD_LGBM_PREDICT in src:
    src = src.replace(OLD_LGBM_PREDICT, NEW_LGBM_PREDICT)
    print("[OK] Fix 5 applied: guard di DirectMultiStepLGBM.predict()")
else:
    print("[WARN] Fix 5: pattern tidak ditemukan")

# ══════════════════════════════════════════════════════════════
#  Tulis hasil patch
# ══════════════════════════════════════════════════════════════

if src != original_src:
    with open(TARGET, "w", encoding="utf-8") as f:
        f.write(src)
    print(f"\n[DONE] {TARGET} berhasil dipatch!")
    print(f"  Backup asli tersimpan di: {backup}")
    print(f"\nSekarang jalankan:")
    print(f"  python run_forecaster_v3.py --all -c commodity_data.csv --no-show")
else:
    print("\n[WARN] Tidak ada perubahan yang diterapkan.")
    print("  Kemungkinan file sudah dipatch atau format berbeda.")
    print(f"  Backup tetap tersimpan di: {backup}")