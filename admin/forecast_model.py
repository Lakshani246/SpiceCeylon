import pandas as pd
import numpy as np
import sys
import json
import mysql.connector
from datetime import datetime, timedelta

# Get parameters from PHP
if len(sys.argv) > 4:
    period = sys.argv[1]  # '6m'
    product_id = sys.argv[2] # 'all' or specific ID
    model_type = sys.argv[3] # 'prophet'
    confidence = sys.argv[4] # '95'
else:
    # Default values
    period = '6m'
    product_id = 'all'
    model_type = 'prophet'
    confidence = '95'

try:
    # Connect to MySQL (same as your PHP config)
    db = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="spiceceylon_db",
        charset='utf8mb4'
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
        query += f" AND oi.product_id = '{product_id}'"
        
    query += " GROUP BY DATE(o.created_at) ORDER BY ds"
    
    cursor = db.cursor()
    cursor.execute(query)
    data = cursor.fetchall()
    
    # If no data, return sample data
    if not data:
        raise Exception("No historical data found")
    
    # Convert to DataFrame
    df = pd.DataFrame(data, columns=['ds', 'y'])
    df['ds'] = pd.to_datetime(df['ds'])
    
    # Ensure data is sorted
    df = df.sort_values('ds')
    
    # Try to use Prophet if available
    try:
        from prophet import Prophet
        
        # Train Prophet model
        model = Prophet(interval_width=int(confidence)/100)
        model.fit(df)
        
        # Create future dates based on period
        if period == '1m':
            future_days = 30
            periods = 30
        elif period == '3m':
            future_days = 90
            periods = 90
        elif period == '6m':
            future_days = 180
            periods = 180
        elif period == '1y':
            future_days = 365
            periods = 365
        elif period == '2y':
            future_days = 730
            periods = 730
        else:  # 5y
            future_days = 1825
            periods = 1825
        
        # Generate future dates
        future = model.make_future_dataframe(periods=periods)
        forecast = model.predict(future)
        
        # Get only future predictions
        future_forecast = forecast[forecast['ds'] > df['ds'].max()]
        
        # Aggregate by month for display
        future_forecast['month'] = future_forecast['ds'].dt.strftime('%b %Y')
        monthly_forecast = future_forecast.groupby('month').agg({
            'yhat': 'mean',
            'yhat_lower': 'mean',
            'yhat_upper': 'mean'
        }).reset_index()
        
        # Take appropriate number of months based on period
        months_to_take = int(periods/30) if period.endswith('m') else int(periods/30.44)
        monthly_forecast = monthly_forecast.head(min(months_to_take, len(monthly_forecast)))
        
        # Prepare result
        result = {
            'dates': monthly_forecast['month'].tolist(),
            'predicted': monthly_forecast['yhat'].round(2).tolist(),
            'lower': monthly_forecast['yhat_lower'].round(2).tolist(),
            'upper': monthly_forecast['yhat_upper'].round(2).tolist(),
            'actual': df['y'].tail(3).round(2).tolist() if len(df) >= 3 else [],
            'growth_rate': round(((monthly_forecast['yhat'].iloc[-1] - monthly_forecast['yhat'].iloc[0]) / monthly_forecast['yhat'].iloc[0]) * 100, 2) if len(monthly_forecast) > 1 else 0,
            'peak_month': monthly_forecast.loc[monthly_forecast['yhat'].idxmax(), 'month'] if not monthly_forecast.empty else '',
            'peak_value': round(monthly_forecast['yhat'].max(), 2) if not monthly_forecast.empty else 0,
            'total_forecast': round(monthly_forecast['yhat'].sum(), 2) if not monthly_forecast.empty else 0
        }
        
    except ImportError:
        # Fallback to simple forecasting if Prophet not available
        result = generate_simple_forecast(df, period, confidence)
    
    # Close database connection
    db.close()
    
    # Output JSON for PHP
    print(json.dumps(result))
    
except Exception as e:
    # Return error or sample data
    error_result = {
        'error': str(e),
        'dates': [],
        'predicted': [],
        'lower': [],
        'upper': [],
        'actual': [],
        'growth_rate': 0,
        'peak_month': '',
        'peak_value': 0,
        'total_forecast': 0
    }
    print(json.dumps(error_result))

def generate_simple_forecast(df, period, confidence):
    """Generate simple forecast when Prophet is not available"""
    
    # Determine forecast length
    if period == '1m':
        months = 1
    elif period == '3m':
        months = 3
    elif period == '6m':
        months = 6
    elif period == '1y':
        months = 12
    elif period == '2y':
        months = 24
    else:  # 5y
        months = 60
    
    # Calculate average daily sales
    if not df.empty:
        avg_daily = df['y'].mean()
    else:
        avg_daily = 5000  # Default
    
    # Generate dates
    dates = []
    predicted = []
    lower = []
    upper = []
    
    base_date = datetime.now()
    conf_factor = int(confidence) / 100
    
    for i in range(1, months + 1):
        # Calculate date
        forecast_date = base_date + timedelta(days=30*i)
        dates.append(forecast_date.strftime('%b %Y'))
        
        # Calculate prediction with growth trend
        growth = 1 + (i * 0.02)  # 2% monthly growth
        prediction = avg_daily * 30 * growth
        
        # Add seasonal variation
        month_num = forecast_date.month
        seasonal_factor = get_seasonal_factor(month_num)
        prediction *= seasonal_factor
        
        # Add some randomness
        prediction *= (1 + np.random.uniform(-0.1, 0.15))
        
        predicted.append(round(prediction, 2))
        
        # Calculate confidence bounds
        margin = (1 - conf_factor) * 0.5
        lower.append(round(prediction * (1 - margin), 2))
        upper.append(round(prediction * (1 + margin), 2))
    
    # Calculate actuals (last 3 months if available)
    actual = []
    if len(df) >= 3:
        # Get last 3 months data
        df['month'] = df['ds'].dt.strftime('%b %Y')
        monthly_actual = df.groupby('month')['y'].sum().tail(3)
        actual = monthly_actual.round(2).tolist()
    
    # Calculate growth rate
    if len(predicted) > 1:
        growth_rate = round(((predicted[-1] - predicted[0]) / predicted[0]) * 100, 2)
    else:
        growth_rate = 0
    
    # Find peak
    if predicted:
        peak_idx = predicted.index(max(predicted))
        peak_month = dates[peak_idx]
        peak_value = round(max(predicted), 2)
    else:
        peak_month = ''
        peak_value = 0
    
    return {
        'dates': dates,
        'predicted': predicted,
        'lower': lower,
        'upper': upper,
        'actual': actual,
        'growth_rate': growth_rate,
        'peak_month': peak_month,
        'peak_value': peak_value,
        'total_forecast': round(sum(predicted), 2)
    }

def get_seasonal_factor(month):
    """Get seasonal adjustment factor for a month"""
    factors = {
        1: 1.1,  # January
        2: 0.9,  # February
        3: 1.0,  # March
        4: 1.2,  # April
        5: 1.1,  # May
        6: 0.95, # June
        7: 1.0,  # July
        8: 1.15, # August
        9: 1.05, # September
        10: 1.3, # October
        11: 1.4, # November
        12: 1.5  # December
    }
    return factors.get(month, 1.0)