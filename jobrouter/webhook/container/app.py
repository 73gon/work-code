from flask import Flask, request, jsonify
import requests
import os
import json

app = Flask(__name__)

# Konfiguration aus .env
BASE_URL = "https://myjobrouter.de/jobrouter/api/rest/v2"
USERNAME = "mardanm"
PASSWORD = "Malik2003-"
PROCESSNAME = "TEST_MALIK"
JOBFUNCTION = "Pedant_Start"

@app.route('/webhook_test', methods=['POST'])
def handle_webhook():
    data = request.json
    print("📩 Webhook erhalten:", data.get('status'))

    processid = data.get('processid')
    status = data.get('status')

    if status != "done":
        print("⚠️ Status ist nicht 'done' → Ignoriert")
        return jsonify({"message": "Ignored due to status"}), 200

    # Neue Session starten (Session-Cookie-Login)
    session = requests.Session()
    login_resp = session.post(f"{BASE_URL}/application/sessions", json={
        "username": USERNAME,
        "password": PASSWORD
    })

    if login_resp.status_code != 201:
        print("❌ Login fehlgeschlagen:", login_resp.status_code, login_resp.text)
        return jsonify({"error": "Login failed"}), 500

    print("✅ Eingeloggt mit Session-ID:", session.cookies.get_dict())

    execute_get = f"{BASE_URL}/application/workitems/inbox?where[jrprocessname][eq]={PROCESSNAME}&where[jrjobfunction][eq]={JOBFUNCTION}"

    exec_resp = session.get(execute_get)

    if exec_resp.status_code != 200:
        print("❌ Fehler beim Abrufen der Workitems:", exec_resp.status_code, exec_resp.text)
        return jsonify({"error": "Failed to retrieve workitems"}), 500

    resp_json = exec_resp.json()

    workflowid = None
    for item in resp_json.get("workitems", []):
        if item.get("jrprocessid") == processid:
            workflowid = item.get("jrworkflowid")
            print("✅ Workitem gefunden:", workflowid)
            break

    if not workflowid:
        print("❌ Kein passendes Workitem gefunden!")
        return jsonify({"error": "No matching workitem found"}), 404

    execute_post = f"{BASE_URL}/application/steps/{workflowid}"
    post_body = {"action": "send"}
    post_resp = session.put(execute_post, json=post_body)
    print("📤 POST an Workflow-Endpoint gesendet:", post_resp.status_code)

    return jsonify({"message": "Webhook processed successfully"}), 200



if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

