import requests

response = requests.post("http://localhost:5000/webhook_test", json={
    "processid": "cb775ac9c6d2d66e226a28fbff4ec3d00000068367",
    "status": "done"
})

print("Response:", response.status_code)
print("JSON:", response.json())
