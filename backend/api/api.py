from flask import Flask, jsonify, request
from flask_cors import CORS
import mysql.connector
from functools import wraps
import datetime
from dateutil.relativedelta import relativedelta

app = Flask(__name__)
# Allow CORS for all domains since it'll be called from InfinityFree
CORS(app) 

# Security: Only requests with this API key will be allowed
API_KEY = "santafe-super-secret-key-2026"

DB_CONFIG = {
    'host': 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com',
    'port': 4000,
    'user': '3QXoXQuJo9As2Sx.root',
    'password': 'ZCu3jpuVMLl4B2Vf',
    'database': 'test',
    'use_pure': True
}

LOCAL_DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'santafe_beach_club',
    'port': 3307
}

def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Exception:
        return mysql.connector.connect(**LOCAL_DB_CONFIG)

def require_api_key(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        provided_key = request.headers.get('X-API-Key')
        if provided_key != API_KEY:
            return jsonify({"error": "Unauthorized. Invalid or missing API key."}), 401
        return f(*args, **kwargs)
    return decorated_function

@app.route('/')
def root_index():
    return jsonify({
        "status": "online",
        "service": "Santa Fe Beach Club Python Analytics API",
        "database": "TiDB Cloud",
        "version": "2.0"
    })

@app.route('/api/dashboard-stats')
@require_api_key
def dashboard_stats():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT COALESCE(SUM(amount),0) AS total_revenue FROM payments WHERE status='verified'")
        total_revenue = cursor.fetchone()['total_revenue']
        
        cursor.execute("SELECT COUNT(*) AS total_bookings FROM bookings")
        total_bookings = cursor.fetchone()['total_bookings']
        
        cursor.execute("SELECT COUNT(*) AS pending_bookings FROM bookings WHERE status='Pending'")
        pending_bookings = cursor.fetchone()['pending_bookings']
        
        cursor.execute("SELECT COALESCE(SUM(guests_count),0) AS total_guests FROM bookings")
        total_guests = cursor.fetchone()['total_guests']
        
        cursor.execute("SELECT ROUND(AVG(DATEDIFF(check_out,check_in)),1) AS avg_stay FROM bookings WHERE status != 'Cancelled'")
        avg_stay = cursor.fetchone()['avg_stay'] or 0
        
        cursor.close()
        conn.close()
        
        return jsonify({
            "total_revenue": float(total_revenue),
            "total_bookings": int(total_bookings),
            "pending_bookings": int(pending_bookings),
            "total_guests": int(total_guests),
            "avg_stay": float(avg_stay)
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/monthly-revenue')
@require_api_key
def monthly_revenue():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        labels = []
        revenue = []
        
        # Calculate for the last 6 months
        for i in range(5, -1, -1):
            target_date = datetime.date.today() - relativedelta(months=i)
            m = target_date.strftime('%Y-%m')
            label = target_date.strftime('%b %Y')
            
            cursor.execute("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status='verified' AND DATE_FORMAT(paid_at,'%Y-%m')=%s", (m,))
            val = cursor.fetchone()['v']
            
            labels.append(label)
            revenue.append(float(val))
            
        cursor.close()
        conn.close()
        
        return jsonify({
            "labels": labels,
            "revenue": revenue
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/status-breakdown')
@require_api_key
def status_breakdown():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        status_counts = {}
        for status in ['Checked In', 'Checked Out', 'Pending', 'Cancelled']:
            cursor.execute("SELECT COUNT(*) AS v FROM bookings WHERE status=%s", (status,))
            status_counts[status] = int(cursor.fetchone()['v'])
            
        cursor.close()
        conn.close()
        
        return jsonify(status_counts)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/top-accommodations')
@require_api_key
def top_accommodations():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT accommodation_name, COUNT(*) AS cnt, SUM(guests_count) AS guests 
            FROM bookings 
            GROUP BY accommodation_name 
            ORDER BY cnt DESC LIMIT 8
        """)
        
        data = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        # Convert Decimals to float/int for JSON serialization
        for row in data:
            row['guests'] = int(row['guests'] or 0)
        
        return jsonify(data)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/payment-methods')
@require_api_key
def payment_methods():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT payment_method, COUNT(*) AS cnt, SUM(amount) AS total 
            FROM payments 
            WHERE status='verified' 
            GROUP BY payment_method 
            ORDER BY total DESC
        """)
        
        data = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        for row in data:
            row['total'] = float(row['total'])
            
        return jsonify(data)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/accommodation-popularity')
@require_api_key
def accommodation_popularity():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT accommodation_name, COUNT(id) as booking_count 
            FROM bookings 
            GROUP BY accommodation_name 
            ORDER BY booking_count DESC
        """)
        
        data = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        return jsonify(data)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/executive-stats')
@require_api_key
def executive_stats():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status='verified'")
        total_revenue = cursor.fetchone()['v']
        
        cursor.execute("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status='verified' AND DATE(paid_at) = CURDATE()")
        daily_revenue = cursor.fetchone()['v']
        
        cursor.execute("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status='verified' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")
        weekly_revenue = cursor.fetchone()['v']
        
        cursor.execute("SELECT COALESCE(SUM(amount),0) AS v FROM payments WHERE status='verified' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")
        monthly_revenue = cursor.fetchone()['v']
        
        cursor.execute("SELECT COUNT(*) AS v FROM bookings")
        total_bookings = cursor.fetchone()['v']
        
        cursor.execute("SELECT COUNT(DISTINCT room_id) AS v FROM bookings WHERE status='Checked In' AND room_id IS NOT NULL")
        occupied_rooms = cursor.fetchone()['v']
        
        cursor.execute("SELECT COUNT(*) AS v FROM rooms")
        total_rooms = cursor.fetchone()['v']
        
        cursor.execute("SELECT COUNT(*) AS v FROM bookings WHERE DATE(check_in)=CURDATE()")
        checkins_today = cursor.fetchone()['v']

        cursor.execute("SELECT COUNT(*) AS v FROM bookings WHERE DATE(check_out)=CURDATE()")
        checkouts_today = cursor.fetchone()['v']
        
        cursor.execute("SELECT COUNT(*) AS v FROM bookings WHERE status='Pending'")
        pending_bookings = cursor.fetchone()['v']

        # Reserved = rooms held by Pending / Pending Payment / Confirmed bookings that overlap today
        cursor.execute("""
            SELECT COUNT(DISTINCT room_id) AS v FROM bookings
            WHERE status IN ('Pending','Pending Payment','Confirmed')
              AND room_id IS NOT NULL
              AND check_in <= CURDATE() AND check_out > CURDATE()
        """)
        reserved_rooms = cursor.fetchone()['v']

        # Pending payments = payment records still in pending state
        cursor.execute("SELECT COUNT(*) AS v FROM payments WHERE status='pending'")
        pending_payments = cursor.fetchone()['v']
        
        cursor.execute("SELECT (SELECT COUNT(*) FROM administrators) + (SELECT COUNT(*) FROM receptionists) AS v")
        total_staff = cursor.fetchone()['v']
        
        occupancy_rate = round((occupied_rooms / total_rooms) * 100) if total_rooms > 0 else 0
        
        cursor.close()
        conn.close()
        
        return jsonify({
            "total_revenue": float(total_revenue),
            "daily_revenue": float(daily_revenue),
            "weekly_revenue": float(weekly_revenue),
            "monthly_revenue": float(monthly_revenue),
            "total_bookings": int(total_bookings),
            "occupied_rooms": int(occupied_rooms),
            "reserved_rooms": int(reserved_rooms),
            "total_rooms": int(total_rooms),
            "checkins_today": int(checkins_today),
            "checkouts_today": int(checkouts_today),
            "pending_bookings": int(pending_bookings),
            "pending_payments": int(pending_payments),
            "total_staff": int(total_staff),
            "occupancy_rate": occupancy_rate
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/weekly-revenue-trajectory')
@require_api_key
def weekly_revenue_trajectory():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        revenue_labels = []
        temp_revenue = {}
        for i in range(6, -1, -1):
            target_date = datetime.date.today() - datetime.timedelta(days=i)
            d = target_date.strftime('%Y-%m-%d')
            label = f"{target_date.strftime('%b')} {target_date.day}" # e.g. Aug 24
            revenue_labels.append(label)
            temp_revenue[d] = 0.0
            
        cursor.execute("""
            SELECT DATE(paid_at) AS pay_date, COALESCE(SUM(amount), 0) AS total 
            FROM payments 
            WHERE status = 'verified' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(paid_at)
        """)
        
        data = cursor.fetchall()
        for row in data:
            date_str = row['pay_date'].strftime('%Y-%m-%d') if isinstance(row['pay_date'], datetime.date) else str(row['pay_date'])
            if date_str in temp_revenue:
                temp_revenue[date_str] = float(row['total'])
                
        revenue_data = []
        for i in range(6, -1, -1):
            target_date = datetime.date.today() - datetime.timedelta(days=i)
            d = target_date.strftime('%Y-%m-%d')
            revenue_data.append(temp_revenue[d])
            
        cursor.close()
        conn.close()
        
        return jsonify({
            "labels": revenue_labels,
            "data": revenue_data
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/room-type-occupancy')
@require_api_key
def room_type_occupancy():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("""
            SELECT 
                r.type,
                COUNT(DISTINCT r.id) AS total_rooms,
                COUNT(DISTINCT CASE WHEN b.status = 'Checked In' THEN r.id END) AS occupied_rooms
            FROM rooms r
            LEFT JOIN bookings b ON r.id = b.room_id
            GROUP BY r.type
            ORDER BY r.type
        """)
        
        data = cursor.fetchall()
        
        labels = []
        occupancy_data = []
        
        for row in data:
            room_type = str(row['type']).replace('_', ' ').title()
            labels.append(room_type)
            total = int(row['total_rooms'])
            used = int(row['occupied_rooms'])
            rate = round((used / total) * 100) if total > 0 else 0
            occupancy_data.append(rate)
            
        cursor.close()
        conn.close()
        
        return jsonify({
            "labels": labels,
            "data": occupancy_data
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/recent-bookings')
@require_api_key
def recent_bookings():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT id, guest_name, accommodation_name, check_in, check_out, status FROM bookings ORDER BY id DESC LIMIT 6")
        data = cursor.fetchall()
        
        # Convert dates to string format
        for row in data:
            if isinstance(row['check_in'], datetime.date):
                row['check_in'] = f"{row['check_in'].strftime('%b')} {row['check_in'].day}, {row['check_in'].year}"
            if isinstance(row['check_out'], datetime.date):
                row['check_out'] = f"{row['check_out'].strftime('%b')} {row['check_out'].day}, {row['check_out'].year}"
                
        cursor.close()
        conn.close()
        
        return jsonify(data)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/recent-logs')
@require_api_key
def recent_logs():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT admin_username, action, details, created_at FROM activity_logs ORDER BY id DESC LIMIT 8")
        data = cursor.fetchall()
        
        for row in data:
            if isinstance(row['created_at'], datetime.datetime):
                dt = row['created_at']
                row['created_at'] = f"{dt.strftime('%b')} {dt.day}, {dt.strftime('%I').lstrip('0')}:{dt.strftime('%M')} {dt.strftime('%p').lower()}"

        cursor.close()
        conn.close()
        
        return jsonify(data)
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/api/daily-summary')
@require_api_key
def daily_summary():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        today = datetime.date.today().strftime('%Y-%m-%d')

        cursor.execute(
            "SELECT COUNT(*) AS v FROM bookings WHERE DATE(created_at) = %s", (today,))
        bookings_today = int(cursor.fetchone()['v'])

        cursor.execute("""
            SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
            FROM payments
            WHERE status = 'verified' AND DATE(paid_at) = %s
        """, (today,))
        row = cursor.fetchone()
        payments_today_count  = int(row['cnt'])
        payments_today_amount = float(row['total'])

        cursor.execute(
            "SELECT COUNT(*) AS v FROM bookings WHERE DATE(check_in) = %s AND status = 'Checked In'", (today,))
        checkins_today = int(cursor.fetchone()['v'])

        cursor.execute(
            "SELECT COUNT(*) AS v FROM bookings WHERE DATE(check_out) = %s AND status = 'Checked Out'", (today,))
        checkouts_today = int(cursor.fetchone()['v'])

        cursor.execute(
            "SELECT COUNT(*) AS v FROM bookings WHERE DATE(updated_at) = %s AND status = 'Cancelled'", (today,))
        cancellations_today = int(cursor.fetchone()['v'])

        cursor.close()
        conn.close()

        return jsonify({
            "date": today,
            "bookings_today":        bookings_today,
            "payments_today_count":  payments_today_count,
            "payments_today_amount": payments_today_amount,
            "checkins_today":        checkins_today,
            "checkouts_today":       checkouts_today,
            "cancellations_today":   cancellations_today,
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == '__main__':
    # Runs the Python API on port 5000
    app.run(port=5000, debug=True)