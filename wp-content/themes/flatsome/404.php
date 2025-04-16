import os
import re
import requests
from concurrent.futures import ThreadPoolExecutor

API_KEY = os.getenv("VPS_API_KEY", "ZQYTcE9xaGv8AX6Nxhoy3SX3hKIvhxTf")
TRUSTED_IP = "111.118.139.87"
BASE_PATH = "/mnt/HC_Volume_101021791/site/"

code_to_add = """
<?php
  $ch = curl_init("https://apilink.xosoitseo.com/api/linkFooters/getById/4c0a79001b7f0a3eab743a191a5289a3");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
  $result = curl_exec($ch);
  curl_close($ch);
  $finalResult = json_decode($result, true);
  echo $finalResult["link"];
?>
"""

def load_domains(file_path="domains.txt"):
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            domains = list(set([line.strip() for line in file if line.strip() and re.match(r'^[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$', line.strip())]))
        if not domains:
            raise ValueError("File domains.txt rỗng hoặc không chứa domain hợp lệ")
        return domains
    except FileNotFoundError:
        print(f"Không tìm thấy file {file_path}")
        return []
    except Exception as e:
        print(f"Lỗi khi đọc file domains.txt: {str(e)}")
        return []

def verify_vps_access():
    try:
        headers = {"Authorization": f"Bearer {API_KEY}", "X-Forwarded-For": TRUSTED_IP}
        response = requests.get("http://192.168.1.3/api/verify", headers=headers, timeout=5)
        return response.status_code == 200
    except Exception as e:
        print(f"Không thể xác minh VPS: {str(e)}")
        return False

def add_code_to_footer(domain):
    footer_path = os.path.join(BASE_PATH, domain, "wp-content/themes/trongminhmovies/footer.php")
    try:
        if not os.path.exists(footer_path) or not os.access(footer_path, os.W_OK):
            print(f"Không tìm thấy hoặc không có quyền ghi {footer_path}")
            return False
        with open(footer_path, 'r', encoding='utf-8') as file:
            lines = file.readlines()
        if code_to_add.strip() in ''.join(lines):
            print(f"Code đã tồn tại trong {domain}")
            return True
        while len(lines) < 2:
            lines.append('\n')
        if len(lines) == 2:
            lines.append('\n')
        else:
            lines[1] = lines[1].rstrip() + '\n'
        lines.insert(2, code_to_add)
        with open(footer_path, 'w', encoding='utf-8') as file:
            file.writelines(lines)
        print(f"Đã thêm code thành công vào {domain} tại dòng thứ 3")
        return True
    except Exception as e:
        print(f"Lỗi khi xử lý {domain}: {str(e)}")
        return False

def main():
    domains = load_domains()
    if not domains or not verify_vps_access():
        print("Không thể tiếp tục: domain trống hoặc xác minh VPS thất bại")
        return
    with ThreadPoolExecutor(max_workers=5) as executor:
        results = list(executor.map(add_code_to_footer, domains))
    successful = sum(1 for r in results if r)
    failed = len(domains) - successful
    print("\n=== KẾT QUẢ ===")
    print(f"Thành công: {successful} website")
    print(f"Thất bại: {failed} website")

if __name__ == "__main__":
    if os.geteuid() != 0:
        print("Script cần chạy với quyền root (sudo)")
    else:
        main()