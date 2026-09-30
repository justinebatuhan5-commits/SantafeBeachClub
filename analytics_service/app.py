"""
analytics_service/app.py
Python Flask Analytics Microservice for Santa Fe Beach Club
Replicates all endpoints from analytics_api.php
Run with: python app.py
"""

import os
import hmac
from pathlib import Path

from flask import Flask, jsonify, request
from flask_cors import CORS
import mysql.connector
from dotenv import load_dotenv
from functools import wraps
from datetime import date, timedelta, datetime
import calendar

app = Flask(__name__)
CORS(app, origins=[])  # This is a server-to-server service, not a public browser API.
load_dotenv(Path(__file__).resolve().parents[1] / '.env')

API_KEY = os.environ.get('ANALYTICS_API_KEY', '')

def require_api_key(endpoint):
    @wraps(endpoint)
    def wrapped(*args, **kwargs):
        provided_key = request.headers.get('X-API-Key', '')
        if not API_KEY or not hmac.compare_digest(provided_key, API_KEY):
            return jsonify({'error': 'Unauthorized.'}), 401
        return endpoint(*args, **kwargs)
    return wrapped

# ── Database Configuration ─────────────────────────────────────────────────────
DB_CONFIG = {
    'host': os.environ.get('DB_HOST', ''),
    'port': int(os.environ.get('DB_PORT', '3306')),
    'user': os.environ.get('DB_USER', ''),
    'password': os.environ.get('DB_PASS', ''),
    'database': os.environ.get('DB_NAME', ''),
    'use_pure': True,
    'autocommit': True,
}

def get_db():
    if not all(DB_CONFIG[key] for key in ('host', 'user', 'password', 'database')):
        raise RuntimeError('Database environment variables are not configured.')
    return mysql.connector.connect(**DB_CONFIG)

def fetchone(cursor, query, params=()):
    cursor.execute(query, params)
    return cursor.fetchone()


# ── Helper: live room occupancy ───────────────────────────────────────────────
def get_checked_in_count(cursor):
    return fetchone(cursor,
        "SELECT COUNT(DISTINCT room_id) FROM bookings WHERE status='Checked In' AND room_id IS NOT NULL"
    )[0]

def get_reserved_count(cursor):
    return fetchone(cursor,
        "SELECT COUNT(DISTINCT room_id) FROM bookings WHERE status='Pending' AND room_id IS NOT NULL"
    )[0]


# ── 1. Executive Stats & KPIs ─────────────────────────────────────────────────
@app.route('/api/executive-stats')
@require_api_key
def executive_stats():
    conn = get_db()
    cursor = conn.cursor()

    daily_rev    = fetchone(cursor, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND DATE(paid_at)=CURDATE()")[0]
    weekly_rev   = fetchone(cursor, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND paid_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY)")[0]
    total_rooms  = fetchone(cursor, "SELECT COUNT(*) FROM rooms")[0]
    occupied     = get_checked_in_count(cursor)
    reserved     = get_reserved_count(cursor)
    occ_rate     = f"{round((occupied/total_rooms)*100,1)}%" if total_rooms > 0 else "0%"
    total_bk     = fetchone(cursor, "SELECT COUNT(*) FROM bookings")[0]
    pending_bk   = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Pending'")[0]
    pending_pay  = fetchone(cursor, "SELECT COUNT(*) FROM payments WHERE status='pending'")[0]
    cin_today    = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Pending' AND DATE(check_in)=CURDATE()")[0]
    cout_today   = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Checked In' AND DATE(check_out)=CURDATE()")[0]

    cursor.close(); conn.close()
    return jsonify({
        'daily_revenue':    float(daily_rev),
        'weekly_revenue':   float(weekly_rev),
        'occupancy_rate':   occ_rate,
        'occupied_rooms':   occupied,
        'total_rooms':      total_rooms,
        'total_bookings':   total_bk,
        'pending_bookings': pending_bk,
        'reserved_rooms':   reserved,
        'pending_payments': pending_pay,
        'checkins_today':   cin_today,
        'checkouts_today':  cout_today,
    })


# ── 2. Weekly Revenue Trajectory ─────────────────────────────────────────────
@app.route('/api/weekly-revenue-trajectory')
@require_api_key
def weekly_revenue_trajectory():
    conn = get_db()
    cursor = conn.cursor()
    labels, data = [], []
    for i in range(6, -1, -1):
        d = date.today() - timedelta(days=i)
        labels.append(d.strftime('%a'))
        row = fetchone(cursor,
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND DATE(paid_at)=%s",
            (d.isoformat(),))
        data.append(float(row[0]))
    cursor.close(); conn.close()
    return jsonify({'labels': labels, 'data': data})


# ── 3. Status Breakdown ───────────────────────────────────────────────────────
@app.route('/api/status-breakdown')
@require_api_key
def status_breakdown():
    conn = get_db()
    cursor = conn.cursor()
    breakdown = {'Checked In': 0, 'Checked Out': 0, 'Pending': 0, 'Cancelled': 0}
    cursor.execute("SELECT status, COUNT(*) FROM bookings GROUP BY status")
    for status, count in cursor.fetchall():
        if status in breakdown:
            breakdown[status] = count
    cursor.close(); conn.close()
    return jsonify(breakdown)


# ── 4. Room Type Occupancy ────────────────────────────────────────────────────
@app.route('/api/room-type-occupancy')
@require_api_key
def room_type_occupancy():
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute("SELECT name, total_rooms FROM room_types ORDER BY id ASC")
    room_types = cursor.fetchall()
    labels, data = [], []
    for (type_name, total) in room_types:
        occ = fetchone(cursor,
            "SELECT COUNT(*) FROM bookings b JOIN rooms r ON b.room_id=r.id WHERE b.status='Checked In' AND r.type=%s",
            (type_name,))[0]
        pct = round((occ / total) * 100) if total > 0 else 0
        labels.append(type_name.replace('_', ' ').title())
        data.append(pct)
    cursor.close(); conn.close()
    return jsonify({'labels': labels, 'data': data})


# ── 5. Recent Bookings ────────────────────────────────────────────────────────
@app.route('/api/recent-bookings')
@require_api_key
def recent_bookings():
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, guest_name, accommodation_name, check_in, status FROM bookings ORDER BY id DESC LIMIT 6")
    rows = cursor.fetchall()
    for row in rows:
        if isinstance(row.get('check_in'), date):
            row['check_in'] = row['check_in'].isoformat()
    cursor.close(); conn.close()
    return jsonify(rows)


# ── 6. Recent Logs Feed ───────────────────────────────────────────────────────
@app.route('/api/recent-logs')
@require_api_key
def recent_logs():
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT admin_username, action, details, created_at FROM activity_logs ORDER BY id DESC LIMIT 6")
    rows = cursor.fetchall()
    for row in rows:
        if isinstance(row.get('created_at'), datetime):
            row['created_at'] = row['created_at'].strftime('%Y-%m-%d %H:%M:%S')
    cursor.close(); conn.close()
    return jsonify(rows)


# ── 7. Daily Summary Widget ───────────────────────────────────────────────────
@app.route('/api/daily-summary')
@require_api_key
def daily_summary():
    conn = get_db()
    cursor = conn.cursor()
    today = date.today().isoformat()
    b_today  = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=CURDATE()")[0]
    pay_row  = fetchone(cursor, "SELECT COUNT(*), COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND DATE(paid_at)=CURDATE()")
    cin      = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Checked In' AND DATE(check_in)=CURDATE()")[0]
    cout     = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Checked Out' AND DATE(check_out)=CURDATE()")[0]
    canc     = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Cancelled' AND DATE(cancelled_at)=CURDATE()")[0]
    cursor.close(); conn.close()
    return jsonify({
        'date': today,
        'bookings_today': b_today,
        'payments_today_count': pay_row[0],
        'payments_today_amount': float(pay_row[1]),
        'checkins_today': cin,
        'checkouts_today': cout,
        'cancellations_today': canc,
    })


# ── 8. Monthly Revenue (Reports Page) ─────────────────────────────────────────
@app.route('/api/monthly-revenue')
@require_api_key
def monthly_revenue():
    conn = get_db()
    cursor = conn.cursor()
    labels, revenue = [], []
    today = date.today()
    for i in range(5, -1, -1):
        month_date = date(today.year, today.month, 1) - timedelta(days=i * 30)
        first_day  = date(month_date.year, month_date.month, 1)
        last_day   = date(month_date.year, month_date.month, calendar.monthrange(month_date.year, month_date.month)[1])
        labels.append(first_day.strftime('%b %Y'))
        row = fetchone(cursor,
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND DATE(paid_at) BETWEEN %s AND %s",
            (first_day.isoformat(), last_day.isoformat()))
        revenue.append(float(row[0]))
    cursor.close(); conn.close()
    return jsonify({'labels': labels, 'revenue': revenue})


# ── 9. Dashboard Stats (Reports Page KPIs) ───────────────────────────────────
@app.route('/api/dashboard-stats')
@require_api_key
def dashboard_stats():
    conn = get_db()
    cursor = conn.cursor()
    total_rev  = fetchone(cursor, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified'")[0]
    total_bk   = fetchone(cursor, "SELECT COUNT(*) FROM bookings")[0]
    pending    = fetchone(cursor, "SELECT COUNT(*) FROM bookings WHERE status='Pending'")[0]
    guests     = fetchone(cursor, "SELECT COALESCE(SUM(guests_count),0) FROM bookings")[0]
    avg_row    = fetchone(cursor, "SELECT AVG(DATEDIFF(check_out, check_in)) FROM bookings WHERE status != 'Cancelled'")
    avg_stay   = round(float(avg_row[0] or 0), 1)
    
    # Occupancy
    total_rooms = fetchone(cursor, "SELECT COUNT(*) FROM rooms")[0] or 0
    checked_in = get_checked_in_count(cursor) or 0
    occ_rate = round((checked_in / total_rooms) * 100, 1)

    today = date.today()
    current_start = today - timedelta(days=29)
    current_end = today + timedelta(days=1)
    previous_start = today - timedelta(days=59)
    previous_end = current_start

    revenue_sql = "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='verified' AND paid_at >= %s AND paid_at < %s"
    booking_sql = "SELECT COUNT(*) FROM bookings WHERE created_at >= %s AND created_at < %s"
    guest_sql = "SELECT COALESCE(SUM(guests_count),0) FROM bookings WHERE created_at >= %s AND created_at < %s"
    occupancy_sql = (
        "SELECT COALESCE(SUM(GREATEST(0, DATEDIFF(LEAST(check_out,%s), GREATEST(check_in,%s)))),0) "
        "FROM bookings WHERE status != 'Cancelled' AND check_in < %s AND check_out > %s"
    )

    def period_value(sql, start, end):
        return float(fetchone(cursor, sql, (start, end))[0] or 0)

    def room_nights(start, end):
        return float(fetchone(cursor, occupancy_sql, (end, start, end, start))[0] or 0)

    def percent_change(current, previous):
        if abs(previous) < 0.00001:
            return 0.0 if abs(current) < 0.00001 else None
        return round(((current - previous) / abs(previous)) * 100, 1)

    current_revenue = period_value(revenue_sql, current_start, current_end)
    previous_revenue = period_value(revenue_sql, previous_start, previous_end)
    current_bookings = period_value(booking_sql, current_start, current_end)
    previous_bookings = period_value(booking_sql, previous_start, previous_end)
    current_guests = period_value(guest_sql, current_start, current_end)
    previous_guests = period_value(guest_sql, previous_start, previous_end)
    capacity = max(1, total_rooms * 30)
    current_period_occupancy = round((room_nights(current_start, current_end) / capacity) * 100, 1)
    previous_period_occupancy = round((room_nights(previous_start, previous_end) / capacity) * 100, 1)

    trend = {
        'period_days': 30,
        'revenue_pct': percent_change(current_revenue, previous_revenue),
        'bookings_pct': percent_change(current_bookings, previous_bookings),
        'guests_pct': percent_change(current_guests, previous_guests),
        'occupancy_pct': percent_change(current_period_occupancy, previous_period_occupancy),
    }
    sparklines = {'revenue': [], 'bookings': [], 'guests': [], 'occupancy': []}
    for offset in range(13, -1, -1):
        day = today - timedelta(days=offset)
        next_day = day + timedelta(days=1)
        sparklines['revenue'].append(period_value(revenue_sql, day, next_day))
        sparklines['bookings'].append(int(period_value(booking_sql, day, next_day)))
        sparklines['guests'].append(int(period_value(guest_sql, day, next_day)))
        daily_nights = room_nights(day, next_day)
        sparklines['occupancy'].append(round((daily_nights / total_rooms) * 100, 1) if total_rooms else 0)

    cursor.close(); conn.close()
    return jsonify({
        'total_revenue': float(total_rev),
        'total_bookings': total_bk,
        'pending_bookings': pending,
        'total_guests': guests,
        'avg_stay': avg_stay,
        'occupancy_rate': occ_rate,
        'occupied_rooms': checked_in,
        'total_rooms': total_rooms,
        'trend': trend,
        'sparklines': sparklines,
    })


# ── 10. Payment Methods (Reports Page) ───────────────────────────────────────
@app.route('/api/payment-methods')
@require_api_key
def payment_methods():
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total "
        "FROM payments WHERE status='verified' GROUP BY payment_method ORDER BY total DESC"
    )
    rows = cursor.fetchall()
    for row in rows:
        row['total'] = float(row['total'])
    cursor.close(); conn.close()
    return jsonify(rows)


# ── 11. Top Accommodations (Reports Page) ────────────────────────────────────
@app.route('/api/top-accommodations')
@require_api_key
def top_accommodations():
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT accommodation_name, COUNT(*) AS cnt, COUNT(DISTINCT guest_email) AS guests "
        "FROM bookings GROUP BY accommodation_name ORDER BY cnt DESC LIMIT 10"
    )
    rows = cursor.fetchall()
    cursor.close(); conn.close()
    return jsonify(rows)


# ── 12. Country Demographics (Guest Origins) ────────────────────────────────
@app.route('/api/country-demographics')
@require_api_key
def country_demographics():
    conn = get_db()
    cursor = conn.cursor(dictionary=True)
    sql = """
        SELECT 
            COALESCE(NULLIF(TRIM(b.guest_country), ''), 'Unknown / Not provided') AS country,
            COUNT(DISTINCT b.id) AS bookings,
            COUNT(DISTINCT NULLIF(LOWER(TRIM(b.guest_email)), '')) AS unique_customers,
            COALESCE(SUM(b.guests_count), 0) AS booked_guests,
            COALESCE(SUM(p.verified_revenue), 0) AS revenue
        FROM bookings b
        LEFT JOIN (
            SELECT booking_id, SUM(amount) AS verified_revenue
            FROM payments
            WHERE status = 'verified'
            GROUP BY booking_id
        ) p ON b.id = p.booking_id
        GROUP BY country
        ORDER BY bookings DESC, revenue DESC
    """
    cursor.execute(sql)
    rows = cursor.fetchall()
    cursor.close(); conn.close()

    total_bookings = sum(r['bookings'] for r in rows) if rows else 0
    total_revenue = sum(float(r['revenue']) for r in rows) if rows else 0.0

    countries = []
    for r in rows:
        b_cnt = int(r['bookings'])
        countries.append({
            'country': r['country'],
            'bookings': b_cnt,
            'unique_customers': int(r['unique_customers']),
            'booked_guests': int(r['booked_guests']),
            'revenue': float(r['revenue']),
            'share_pct': round((b_cnt / total_bookings) * 100, 1) if total_bookings > 0 else 0
        })

    return jsonify({
        'countries': countries,
        'total_bookings': total_bookings,
        'total_revenue': total_revenue,
        'total_origins': len(countries)
    })


if __name__ == '__main__':
    print("=" * 55)
    print("  Santa Fe Beach Club - Python Analytics Service")
    print("  Running on http://127.0.0.1:5000")
    print("  Press CTRL+C to stop.")
    print("=" * 55)
    app.run(host='127.0.0.1', port=5000, debug=False)
