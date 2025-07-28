# คู่มือการตั้งค่า LimeSurvey API

## ภาพรวม

ระบบ TPAK DQ System สามารถเชื่อมต่อกับ LimeSurvey API เพื่อดึงข้อมูล Survey Structure โดยตรงจาก LimeSurvey Server

## ขั้นตอนการตั้งค่า

### 1. เปิดใช้งาน LimeSurvey Remote Control

1. เข้าไปที่ LimeSurvey Admin Panel
2. ไปที่ **Configuration** > **Global Settings**
3. ค้นหา **Remote Control** หรือ **RPC Interface**
4. เปิดใช้งาน **Enable RPC interface**
5. บันทึกการตั้งค่า

### 2. ตั้งค่า API URL

API URL จะอยู่ในรูปแบบ:
```
https://your-limesurvey-domain.com/admin/remotecontrol
```

ตัวอย่าง:
- `https://survey.example.com/admin/remotecontrol`
- `https://limesurvey.company.com/admin/remotecontrol`

### 3. สร้าง API User

1. ไปที่ **Users** > **Manage Users**
2. สร้าง User ใหม่สำหรับ API
3. กำหนด Role เป็น **Administrator** หรือ **Survey Administrator**
4. บันทึก Username และ Password

### 4. ตั้งค่าใน WordPress

1. เข้าไปที่ WordPress Admin
2. ไปที่ **TPAK Verification** > **API Settings**
3. กรอกข้อมูล:
   - **API URL**: URL ของ LimeSurvey Remote Control
   - **Username**: Username ของ API User
   - **Password**: Password ของ API User
4. กด **Save Settings**

### 5. ทดสอบการเชื่อมต่อ

1. กดปุ่ม **Test Connection**
2. หากเชื่อมต่อสำเร็จ จะแสดง Session Key
3. หากไม่สำเร็จ ให้ตรวจสอบ:
   - API URL ถูกต้องหรือไม่
   - Username/Password ถูกต้องหรือไม่
   - LimeSurvey Remote Control เปิดใช้งานหรือไม่

## การใช้งาน

### ดึง Survey Structure จาก API

1. ไปที่ **TPAK Verification** > **Survey Structure**
2. ในส่วน **วิธีที่ 2: Sync จาก LimeSurvey API**
3. กรอก **Survey ID** ที่ต้องการดึง
4. กด **Sync จาก API**
5. ระบบจะดึงข้อมูลและบันทึกลงฐานข้อมูล

### การจัดการ Survey

- **Sync**: ดึงข้อมูลล่าสุดจาก LimeSurvey
- **View**: ดูข้อมูล Survey Structure
- **Delete**: ลบข้อมูล Survey Structure

## การแก้ไขปัญหา

### ปัญหาที่พบบ่อย

#### 1. API Connection Failed
**สาเหตุ:**
- API URL ไม่ถูกต้อง
- Username/Password ไม่ถูกต้อง
- LimeSurvey Remote Control ไม่เปิดใช้งาน

**วิธีแก้:**
- ตรวจสอบ API URL
- ตรวจสอบ Username/Password
- เปิดใช้งาน Remote Control ใน LimeSurvey

#### 2. Survey Not Found
**สาเหตุ:**
- Survey ID ไม่ถูกต้อง
- Survey ไม่มีอยู่ใน LimeSurvey
- API User ไม่มีสิทธิ์เข้าถึง Survey

**วิธีแก้:**
- ตรวจสอบ Survey ID
- ตรวจสอบสิทธิ์ของ API User
- ตรวจสอบว่า Survey มีอยู่ใน LimeSurvey

#### 3. Permission Denied
**สาเหตุ:**
- API User ไม่มีสิทธิ์เพียงพอ
- LimeSurvey ไม่อนุญาตการเข้าถึง

**วิธีแก้:**
- เปลี่ยน Role ของ API User เป็น Administrator
- ตรวจสอบการตั้งค่า LimeSurvey

### การ Debug

#### 1. ตรวจสอบ Log
ดู WordPress Debug Log:
```
wp-content/debug.log
```

#### 2. ตรวจสอบ Network
ใช้ Browser Developer Tools ดู Network Requests

#### 3. ตรวจสอบ LimeSurvey Log
ดู LimeSurvey Error Log:
```
application/runtime/logs/
```

## ความปลอดภัย

### ข้อแนะนำด้านความปลอดภัย

1. **ใช้ HTTPS**: ใช้ HTTPS สำหรับ API URL
2. **จำกัดสิทธิ์**: สร้าง API User ที่มีสิทธิ์จำกัด
3. **เปลี่ยน Password**: เปลี่ยน Password เป็นประจำ
4. **Firewall**: จำกัดการเข้าถึง API จาก IP ที่อนุญาต

### การตั้งค่า Firewall

หากใช้ Firewall ให้อนุญาต:
```
TCP Port 443 (HTTPS)
TCP Port 80 (HTTP) - หากไม่ใช้ HTTPS
```

## การตั้งค่าขั้นสูง

### Custom API Endpoint

หาก LimeSurvey ใช้ Custom Endpoint:
```
https://your-domain.com/custom-api-endpoint
```

### Proxy Configuration

หากต้องใช้ Proxy:
```php
// ใน wp-config.php
define('WP_PROXY_HOST', 'proxy.example.com');
define('WP_PROXY_PORT', '8080');
define('WP_PROXY_USERNAME', 'username');
define('WP_PROXY_PASSWORD', 'password');
```

### SSL Certificate

หากมีปัญหา SSL Certificate:
```php
// ใน wp-config.php
define('WP_HTTP_BLOCK_EXTERNAL', false);
```

## การทดสอบ

### Test Survey

สร้าง Test Survey ใน LimeSurvey:
1. สร้าง Survey ใหม่
2. เพิ่มคำถามหลายประเภท
3. ใช้ Survey ID นี้ในการทดสอบ

### Test Questions

ทดสอบคำถามประเภทต่างๆ:
- Single Choice
- Multiple Choice
- Text Input
- Matrix Questions
- Array Questions

## การสนับสนุน

หากมีปัญหา:
1. ตรวจสอบคู่มือนี้
2. ดู Error Log
3. ติดต่อผู้ดูแลระบบ
4. ส่งรายงานปัญหา

## ข้อมูลเพิ่มเติม

- [LimeSurvey Remote Control Documentation](https://manual.limesurvey.org/RemoteControl_2_API)
- [WordPress HTTP API](https://developer.wordpress.org/reference/functions/wp_remote_post/)
- [TPAK DQ System Documentation](README-SURVEY-DISPLAY.md) 