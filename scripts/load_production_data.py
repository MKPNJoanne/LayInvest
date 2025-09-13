import pandas as pd
from sqlalchemy import create_engine

use_cols = [
    'ddate', 'week_no', 'female_count', 'f_died_count', 'f_cul_count',
    'female_feed', 'egg_small', 'egg_broken', 'white_eggs',
    'brown_eggs', 'egg_weight'
]
df = pd.read_excel("../data/Research_Data.xlsx", sheet_name="Production Data", usecols=use_cols)
# Debug: Show the raw headers exactly as Pandas sees them
#print("Raw Excel columns:", df.columns.tolist())

# Clean hidden spaces just in case
df.columns = df.columns.str.strip()


flock_id = 1
flock_ids = []

previous_week = None
previous_count = None

for idx, row in df.iterrows():
    week_no = row['week_no']
    female_count = row['female_count']

    # If week resets OR big jump in female count → new flock
    if (previous_week is not None and week_no < previous_week) or \
       (previous_count is not None and female_count >= 5000 and female_count > previous_count):
        flock_id += 1

    flock_ids.append(flock_id)
    previous_week = week_no
    previous_count = female_count

df['flock_id'] = flock_ids

# 2. Rename columns to match DB
df = df.rename(columns={
    'ddate': 'ddate',
    'week_no': 'week_no',
    'female_count': 'female_count',
    'f_died_count': 'f_died_count',
    'f_cul_count': 'f_cul_count',
    'female_feed': 'female_feed',
    'egg_small': 'egg_small',
    'egg_broken': 'egg_broken',
    # 'egg_shell': 'egg_shell',
    'white_eggs': 'white_eggs',
    'brown_eggs': 'brown_eggs',
    'egg_weight': 'egg_weight'
})

# 3. Convert numeric columns safely
numeric_cols = [
    'female_count', 'f_died_count', 'f_cul_count',
    'female_feed', 'egg_small', 'egg_broken',
    'white_eggs', 'brown_eggs', 'egg_weight'
]
for col in numeric_cols:
    df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)

# Convert date separately
df['ddate'] = pd.to_datetime(df['ddate'], errors='coerce')

# Add flock_id
df['flock_id'] = 1

# 4. Connect to PostgreSQL
engine = create_engine("postgresql+psycopg2://postgres:admin123@localhost:5432/layinvest_db")

# 5. Insert into DB
df.to_sql('production_data', engine, schema='core', if_exists='append', index=False)

print(f"✅ Inserted {len(df)} rows into core.production_data")
