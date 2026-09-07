"""
Test script for the FastAPI NLP Microservice.
Fires 12 complaints (3 per category) with mixed Filipino-English,
varied tone and sentiment. Polls for results and prints a summary.
"""

import time
import requests

BASE_URL = "http://localhost:8000/api/v1"

TEST_COMPLAINTS = [
    # ── BILLING ─────────────────────────────────────────────────────────────
    {
        "category": "Metering",
        "tone": "Happy",
        "sentiment": "Positive",
        "complaint_text": (
            "mga hayup kayo mga hampaslupa walang silbi bobo kaba"
            "thank you dahil inayos mo ang metro ko kahit napaka bobo mo at walang silbi"
            "wala akong paki sa inyo mag sara na sana company nyo!"
        ),
    },
    {
        "category": "Billing",
        "tone": "Angry",
        "sentiment": "Negative",
        "complaint_text": (
            "Grabe naman kayo! Dalawang beses na kayong nag-charge sa aking account "
            "ngayong buwan at wala pa ring nagresolba. This is absolutely unacceptable "
            "and I demand a full refund immediately!"
        ),
    },
    {
        "category": "Billing",
        "tone": "Frustrated but polite",
        "sentiment": "Negative",
        "complaint_text": (
            "Good day. I just want to follow up on my billing concern. "
            "Yung bill ko po kasi ay tumaas ng husto this month — from 800 to almost 4,000 pesos — "
            "pero walang pagbabago sa aming consumption. Sana po ay ma-check ninyo."
        ),
    },
    {
        "category": "Billing",
        "tone": "Sarcastic",
        "sentiment": "Negative",
        "complaint_text": (
            "Wow, congrats sa bagong billing system ninyo. Nagbayad na ko last week, "
            "may resibo pa ko, pero may 'overdue' pa rin sa portal ko. "
            "Talagang impressive ang technology ninyo."
        ),
    },

    # ── WATER QUALITY ────────────────────────────────────────────────────────
    {
        "category": "Water Quality",
        "tone": "Alarmed",
        "sentiment": "Negative",
        "complaint_text": (
            "The water from our faucet smells like rust at may kasamang brown particles. "
            "Nagtitimpla pa lang ako ng kape when I noticed it. "
            "This is a serious health hazard — my kids drink this water!"
        ),
    },
    {
        "category": "Water Quality",
        "tone": "Worried",
        "sentiment": "Negative",
        "complaint_text": (
            "Nagkasakit po ang aming buong pamilya after uminom ng tubig galing sa gripo. "
            "May matapang na chemical taste siya at maulap ang kulay. "
            "We went to the clinic already but we are really concerned about the water source."
        ),
    },
    {
        "category": "Water Quality",
        "tone": "Calm / matter-of-fact",
        "sentiment": "Neutral",
        "complaint_text": (
            "Just reporting that the water in our barangay has had a faint chlorine odor "
            "for the past three days. Hindi naman masyadong malala pero noticeable pa rin. "
            "Gusto ko lang malaman kung may maintenance work ba na nagaganap sa aming area."
        ),
    },

    # ── METERING ────────────────────────────────────────────────────────────
    {
        "category": "Metering",
        "tone": "Confused / suspicious",
        "sentiment": "Negative",
        "complaint_text": (
            "Hindi ko maintindihan ang aking bill. Ang reading sa aming water meter "
            "ay 132 cubic meters, pero ang nakalagay sa bill ay 178. "
            "May pagkakaiba ng 46 cubic meters — that's a huge discrepancy and I suspect "
            "may mali sa inyo."
        ),
    },
    {
        "category": "Metering",
        "tone": "Urgent",
        "sentiment": "Negative",
        "complaint_text": (
            "Our meter has completely stopped moving for two weeks na. "
            "Gumagamit kami ng tubig araw-araw pero walang nagbabago sa reading. "
            "I need this checked ASAP because I'm worried we'll get a surprise bill later."
        ),
    },
    {
        "category": "Metering",
        "tone": "Neutral / informational",
        "sentiment": "Neutral",
        "complaint_text": (
            "I would like to report that the meter box cover sa labas ng aming bahay "
            "is broken and exposed. Pwedeng mapinsala ng sinuman lalo na yung mga bata. "
            "Requesting for a replacement or repair at your earliest convenience."
        ),
    },

    # ── OPERATIONS ──────────────────────────────────────────────────────────
    {
        "category": "Operations",
        "tone": "Desperate",
        "sentiment": "Negative",
        "complaint_text": (
            "Wala na kaming tubig dito sa aming street for almost 3 days already! "
            "Wala man lang notification na may interruption. Paano na kami magluluto "
            "at maliligo? This is a basic necessity and we are suffering because of your negligence!"
        ),
    },
    {
        "category": "Operations",
        "tone": "Concerned / civic",
        "sentiment": "Negative",
        "complaint_text": (
            "There is a broken water main near the corner of our street. "
            "Bumibigla na yung tubig sa daan at nag-aapaw na. "
            "Nagpadala na rin kami ng report sa barangay pero wala pa ring response "
            "from your end. Please send a repair crew immediately."
        ),
    },
    {
        "category": "Operations",
        "tone": "Resigned / tired",
        "sentiment": "Negative",
        "complaint_text": (
            "Sinabihan kayo na magtatapos ang scheduled maintenance by 6am ngayong umaga. "
            "Alas-dose na ng tanghali, wala pa ring tubig. "
            "This keeps happening every few months. "
            "Sana naman totoo ang time estimates ninyo para makapag-prepare kami."
        ),
    },
]

POLL_INTERVAL = 2   # seconds between status checks
MAX_RETRIES   = 15  # give up after this many polls per job


def submit_complaint(complaint: dict) -> str | None:
    """POST complaint and return job_id."""
    try:
        resp = requests.post(
            f"{BASE_URL}/process-complaint",
            json={"complaint_text": complaint["complaint_text"]},
            timeout=10,
        )
        resp.raise_for_status()
        job_id = resp.json().get("job_id")
        return job_id
    except Exception as e:
        print(f"  [ERROR] Submit failed: {e}")
        return None


def poll_result(job_id: str) -> dict | None:
    """Poll until result is ready or retries exhausted."""
    for _ in range(MAX_RETRIES):
        try:
            resp = requests.get(f"{BASE_URL}/result/{job_id}", timeout=10)
            resp.raise_for_status()
            data = resp.json()
            status = data.get("status", "")
            if status not in ("pending", "processing"):
                return data
        except Exception as e:
            print(f"  [ERROR] Poll failed: {e}")
            return None
        time.sleep(POLL_INTERVAL)
    print(f"  [TIMEOUT] Job {job_id} did not complete in time.")
    return None


def run_tests():
    print("=" * 70)
    print("  NLP MICROSERVICE — COMPLAINT TEST SUITE")
    print("=" * 70)

    results_summary = []

    for i, complaint in enumerate(TEST_COMPLAINTS, 1):
        print(f"\n[{i:02d}/12] Category: {complaint['category']}  |  "
              f"Tone: {complaint['tone']}  |  Sentiment: {complaint['sentiment']}")
        print(f"  Text: {complaint['complaint_text'][:80]}...")

        # Submit
        job_id = submit_complaint(complaint)
        if not job_id:
            results_summary.append({**complaint, "job_id": None, "result": None})
            continue
        print(f"  Job ID: {job_id}")

        # Poll
        result = poll_result(job_id)
        if result:
            pred_cat    = result.get("category", "N/A")
            pred_sent   = result.get("sentiment", "N/A")
            severity    = result.get("severity", "N/A")
            confidence  = result.get("category_confidence", None)

            conf_str = f"{confidence:.2f}" if isinstance(confidence, float) else str(confidence)
            match = "✓" if pred_cat == complaint["category"] else "✗"

            print(f"  {match} Predicted Category : {pred_cat}  (expected: {complaint['category']})")
            print(f"    Sentiment  : {pred_sent}  (expected: {complaint['sentiment']})")
            print(f"    Severity   : {severity}")
            print(f"    Confidence : {conf_str}")
        else:
            print("  [NO RESULT]")

        results_summary.append({
            **complaint,
            "job_id": job_id,
            "result": result,
        })

    # ── SUMMARY TABLE ────────────────────────────────────────────────────────
    print("\n" + "=" * 70)
    print("  SUMMARY")
    print("=" * 70)
    print(f"  {'#':<4} {'Expected Cat':<16} {'Predicted Cat':<16} {'Match':<6} {'Sentiment':<12} {'Severity'}")
    print("  " + "-" * 66)

    correct = 0
    for i, item in enumerate(results_summary, 1):
        r = item.get("result") or {}
        pred_cat  = r.get("category", "N/A")
        pred_sent = r.get("sentiment", "N/A")
        severity  = r.get("severity", "N/A")
        match     = pred_cat == item["category"]
        if match:
            correct += 1
        mark = "✓" if match else "✗"
        print(f"  {i:<4} {item['category']:<16} {pred_cat:<16} {mark:<6} {pred_sent:<12} {severity}")

    total = len(results_summary)
    print(f"\n  Classification accuracy: {correct}/{total} ({correct/total*100:.1f}%)")
    print("=" * 70)


if __name__ == "__main__":
    run_tests()