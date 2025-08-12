#!C:\Research\LayInvest\venv\Scripts\python.exe
from dotenv import load_dotenv
import os, sys, argparse, json
import pandas as pd
import psycopg2
from psycopg2.extras import execute_values
from prophet import Prophet

DEFAULT_SERIES = [
    "feed_starter","feed_grower","feed_layer",
    "doc_price","cull_price",
    "egg_price_white","egg_price_brown","egg_price_small",
]

load_dotenv()  # read PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD

def get_conn():
    host = os.getenv('PGHOST', 'localhost')
    port = os.getenv('PGPORT', '5432')
    db   = os.getenv('PGDATABASE', 'layinvest_db')
    user = os.getenv('PGUSER', 'postgres')
    pwd  = os.getenv('PGPASSWORD', 'admin123')
    dsn = f"host={host} port={port} dbname={db} user={user} password={pwd}"
    return psycopg2.connect(dsn)

def load_history(cur, series_name: str) -> pd.DataFrame:
    cur.execute("""
        SELECT ds, value
        FROM oc.price_history_raw
        WHERE series_name = %s
        ORDER BY ds
    """, (series_name,))
    rows = cur.fetchall()
    if not rows:
        return pd.DataFrame(columns=["ds","y"])
    df = pd.DataFrame(rows, columns=["ds","y"])
    df["ds"] = pd.to_datetime(df["ds"])
    df["y"]  = pd.to_numeric(df["y"], errors="coerce")
    return df.dropna(subset=["ds","y"])

def fit_and_forecast(df_hist: pd.DataFrame, start_date: str, weeks: int, series_name: str) -> pd.DataFrame:
    weekly = True if series_name == "cull_price" else False  # daily-ish history
    m = Prophet(weekly_seasonality=weekly, daily_seasonality=False)
    m.fit(df_hist.rename(columns={"ds":"ds","y":"y"}))
    future = pd.DataFrame({"ds": pd.date_range(start=start_date, periods=weeks, freq="7D")})
    fcst = m.predict(future)[["ds","yhat"]].rename(columns={"yhat":"value"})
    fcst["week_no"] = range(1, len(fcst)+1)
    fcst["ds"] = fcst["ds"].dt.date
    return fcst[["week_no","ds","value"]]

def upsert_forecast(cur, series_name: str, unit: str, model_version: str, rows: pd.DataFrame):
    if rows.empty:
        return
    values = [(series_name, int(r.week_no), r.ds, float(r.value), unit, model_version)
              for r in rows.itertuples(index=False)]
    execute_values(cur, """
        INSERT INTO oc.price_forecasts (series_name, week_no, ds, value, unit, model_version)
        VALUES %s
        ON CONFLICT (series_name, week_no) DO UPDATE
          SET ds = EXCLUDED.ds,
              value = EXCLUDED.value,
              unit = EXCLUDED.unit,
              model_version = EXCLUDED.model_version
    """, values)

def infer_unit(series_name: str) -> str:
    if series_name.startswith("feed_"): return "LKR/kg"
    if series_name in ("doc_price","cull_price"): return "LKR/bird"
    if series_name.startswith("egg_price"): return "LKR/egg"
    return "LKR"

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--start-date", required=True)
    ap.add_argument("--weeks", type=int, default=100)
    ap.add_argument("--series", nargs="+", default=DEFAULT_SERIES)
    args = ap.parse_args()

    conn = get_conn()
    summary = []
    try:
        with conn:
            with conn.cursor() as cur:
                for s in args.series:
                    hist = load_history(cur, s)
                    if len(hist) < 6:
                        summary.append({"series": s, "status":"skipped (not enough history)","rows_hist":len(hist)})
                        continue
                    fc = fit_and_forecast(hist, args.start_date, args.weeks, s)
                    upsert_forecast(cur, s, infer_unit(s), "prophet-1.1", fc)
                    summary.append({"series": s, "status":"ok","rows_hist":len(hist),"rows_fc":len(fc)})
    finally:
        conn.close()
    print(json.dumps({"start_date": args.start_date, "weeks": args.weeks, "summary": summary}, indent=2))

if __name__ == "__main__":
    main()
