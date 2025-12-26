import pandas as pd
import numpy as np
from prophet import Prophet
import sys
import json
import mysql.connector

# Get parameters from PHP
period = sys.argv[1]  # '6m'
product_id = sys.argv[2] # 'all' or specific ID
model_type = sys.argv[3] # 'prophet'

# Connect to MySQL (same as your PHP config)
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="spiceceylon_db"
)

# Query actual historical data
query = """
    SELECT DATE(o.created_at) as ds, 
           SUM(oi.total_price) as y
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.status = 'Delivered'
"""
if product_id != 'all':
    query += f" AND oi.product_id = {product_id}"
    
query += " GROUP BY DATE(o.created_at) ORDER BY ds"

cursor = db.cursor()
cursor.execute(query)
data = cursor.fetchall()

# Convert to DataFrame
df = pd.DataFrame(data, columns=['ds', 'y'])
df['ds'] = pd.to_datetime(df['ds'])

# Train Prophet model
model = Prophet()
model.fit(df)

# Create future dates based on period
if period == '1m':
    future_days = 30
elif period == '6m':
    future_days = 180
# ... etc

future = model.make_future_dataframe(periods=future_days)
forecast = model.predict(future)

# Get predictions
predictions = forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']].tail(future_days)

# Convert to JSON for PHP
result = {
    'dates': predictions['ds'].dt.strftime('%M %Y').tolist(),
    'predicted': predictions['yhat'].round(2).tolist(),
    'lower': predictions['yhat_lower'].round(2).tolist(),
    'upper': predictions['yhat_upper'].round(2).tolist()
}

print(json.dumps(result))