#!C:\Research\LayInvest\venv\Scripts\python.exe
from dotenv import load_dotenv
import os, argparse, psycopg2
from psycopg2.extras import execute_values
import pandas as pd
from prophet import Prophet
import numpy as np
import logging


# Config

DEFAULT_SERIES = [
    "feed_starter","feed_grower","feed_layer",
    "doc_price","cull_price",
    "egg_price_white","egg_price_brown","egg_price_small",
]

BOUNDS = {
    "feed_starter": {"min": 100, "max": 400},
    "feed_grower":  {"min": 90, "max": 400},
    "feed_layer":   {"min": 80, "max": 400},
    "doc_price":    {"min": 60, "max": 550},
    "cull_price":   {"min": 100, "max": 600},
    "egg_price_white": {"min": 12, "max": 65},
    "egg_price_brown": {"min": 13, "max": 65},
    "egg_price_small": {"min": 10, "max": 50},
}

MODEL_VERSION = "prophet-1.1"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)


# DB Connection

load_dotenv()  # load PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD

def get_conn():
    return psycopg2.connect(
        host=os.getenv("PGHOST", "localhost"),
        port=os.getenv("PGPORT", "5432"),
        dbname=os.getenv("PGDATABASE", "layinvest_db"),
        user=os.getenv("PGUSER", "postgres"),
        password=os.getenv("PGPASSWORD", "admin123")
    )


# Load history (Option 1, no warnings)

def load_history(series_name):
    sql = """
        SELECT ds, value
        FROM oc.price_history_raw
        WHERE series_name = %s
        ORDER BY ds
    """
    conn = get_conn()
    cur = conn.cursor()
    cur.execute(sql, (series_name,))
    rows = cur.fetchall()
    cur.close()
    conn.close()
    return pd.DataFrame(rows, columns=["ds", "y"])

# Forecast
def fit_and_forecast(df, start_date, weeks, series_name):
    df["ds"] = pd.to_datetime(df["ds"])
    df["y"] = pd.to_numeric(df["y"], errors="coerce").astype(float)
    df = df.dropna(subset=["ds","y"])

    # Bounds for this series
    bounds = BOUNDS.get(series_name, {"min": 0.0, "max": float(df["y"].max() * 2 or 1.0)})
    min_cap = float(bounds["min"])
    max_cap = float(bounds["max"])

    # --- clamp history BEFORE fitting (stabilizes model) ---
    df["y"] = df["y"].clip(lower=min_cap, upper=max_cap)

    # Logistic growth wants floor/cap on history & future
    df["floor"] = min_cap
    df["cap"]   = max_cap

    model = Prophet(
        growth="logistic",
        yearly_seasonality=True,
        weekly_seasonality=False,
        daily_seasonality=False
    )
    model.fit(df)

    # Build future horizon: Prophet includes history rows; we’ll filter by start_date after
    future = model.make_future_dataframe(periods=weeks, freq="W")
    future["floor"] = min_cap
    future["cap"]   = max_cap

    forecast = model.predict(future)

    # --- clamp predictions AFTER predicting ---
    forecast["yhat"] = forecast["yhat"].clip(lower=min_cap, upper=max_cap)

    # Only keep rows on/after requested start_date
    forecast = forecast[forecast["ds"] >= pd.to_datetime(start_date)]

    # Add week_no starting at 1
    forecast = forecast.reset_index(drop=True)
    forecast["week_no"] = (forecast.index + 1).astype(int)

    return forecast[["week_no","ds","yhat"]]


# Save forecast

def save_forecast(series_name, forecast):
    # Final safety clamp (belt & suspenders)
    b = BOUNDS.get(series_name, None)
    if b is not None:
        forecast["yhat"] = forecast["yhat"].clip(lower=b["min"], upper=b["max"])

    tuples = [
        (
            series_name,
            int(row.week_no),
            row.ds.date(),
            float(row.yhat),
            ("LKR/bird" if ("doc" in series_name or "cull" in series_name)
             else ("LKR/kg" if "feed" in series_name else "LKR/egg")),
            MODEL_VERSION
        )
        for row in forecast.itertuples(index=False)
    ]

    sql = """
        INSERT INTO oc.price_forecasts (series_name, week_no, ds, value, unit, model_version)
        VALUES %s
        ON CONFLICT (series_name, week_no)
        DO UPDATE SET
            ds = EXCLUDED.ds,
            value = EXCLUDED.value,
            unit = EXCLUDED.unit,
            model_version = EXCLUDED.model_version
    """

    conn = get_conn()
    cur = conn.cursor()
    execute_values(cur, sql, tuples)
    conn.commit()
    cur.close()
    conn.close()

    # quick sanity log for the saved range
    vmin = float(forecast["yhat"].min()) if not forecast.empty else None
    vmax = float(forecast["yhat"].max()) if not forecast.empty else None
    logging.info(f"Saved {len(tuples)} rows for {series_name} (min={vmin}, max={vmax})")


# Main

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--start-date", required=True, help="Forecast start date (YYYY-MM-DD)")
    parser.add_argument("--weeks", type=int, default=110, help="Number of weeks to forecast")
    parser.add_argument("--series", nargs="*", default=DEFAULT_SERIES, help="Series names")
    args = parser.parse_args()

    for s in args.series:
        logging.info(f"=== Forecasting {s} ===")
        hist = load_history(s)
        if hist.empty:
            logging.warning(f"No history for {s}, skipping")
            continue
        fc = fit_and_forecast(hist, args.start_date, args.weeks, s)
        save_forecast(s, fc)

if __name__ == "__main__":
    main()
