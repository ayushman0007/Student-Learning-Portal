from flask import Flask, request, jsonify
import openai  # For AI responses (or use rule-based)

app = Flask(__name__)

# Mock knowledge base (replace with real data)
QA_PAIRS = {
    "exam schedule": "Final exams start on December 15. Check your dashboard for details.",
    "assignment deadline": "The next assignment is due on November 30 at 11:59 PM.",
    "library hours": "The library is open from 8 AM to 8 PM on weekdays.",
    "default": "I can help with exam schedules, deadlines, and library hours. Ask me anything!"
}

@app.route('/ask', methods=['GET'])
def ask():
    question = request.args.get('q', '').lower()
    
    # Rule-based response (or use OpenAI for smarter answers)
    answer = QA_PAIRS.get(question, QA_PAIRS["default"])
    
    # For OpenAI (uncomment if you have an API key):
    # response = openai.ChatCompletion.create(
    #     model="gpt-3.5-turbo",
    #     messages=[{"role": "user", "content": question}]
    # )
    # answer = response.choices[0].message.content
    
    return jsonify({"answer": answer})

if __name__ == '__main__':
    app.run(port=5000)  # Run on http://localhost:5000