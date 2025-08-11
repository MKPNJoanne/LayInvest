import pandas as pd
from sqlalchemy import create_engine

# --- DB CONNECTION ---
engine = create_engine("postgresql+psycopg2://postgres:admin123@localhost:5432/layinvest_db")

# --- READ EXCEL ---
# Change sheet_name to your actual sheet name for feed consumption
df = pd.read_excel("../data/Research_Data.xlsx", sheet_name="Feed Consumption")

# --- RENAME COLUMNS TO MATCH DB ---
df = df.rename(columns={
    'week': 'week_no',
    'feed_type': 'feed_type',
    'std_weight': 'std_weight_g',
    'feed': 'feed_g',
    'gain': 'gain_g',
    'laying_per': 'laying_percentage',
    'egg_weight': 'egg_weight_g'
})

# --- CONVERT TYPES ---
for col in ['std_weight_g', 'feed_g', 'gain_g', 'laying_percentage', 'egg_weight_g']:
    df[col] = pd.to_numeric(df[col], errors='coerce')

df['week_no'] = pd.to_numeric(df['week_no'], errors='coerce').astype('Int64')

# --- ADD FLOCK ID ---
df['flock_id'] = 1

# --- LOAD INTO POSTGRES ---
df.to_sql('feed_consumption', engine, schema='core', if_exists='append', index=False)

print("✅ Feed consumption data loaded successfully into core.feed_consumption")