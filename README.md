# Kurs Takip Masaüstü Uygulaması

Bu proje, bir PHP web uygulamasının yerel bir masaüstü uygulaması olarak çalıştırılması için yapılandırılmıştır.

## Gereksinimler

- **PHP**: Bu uygulamayı çalıştırmak için bilgisayarınızda PHP'nin yüklü olması gerekmektedir. PHP'yi [resmi web sitesinden](https://www.php.net/downloads.php) indirebilirsiniz. Yükleme sırasında, PHP'yi sisteminizin PATH ortam değişkenine eklediğinizden emin olun.

## Nasıl Çalıştırılır?

1.  Bu proje klasörünü bilgisayarınızda istediğiniz bir yere kopyalayın.
2.  `start-server.bat` dosyasına çift tıklayın.

Bu işlem, PHP'nin dahili web sunucusunu başlatacak ve uygulamayı varsayılan web tarayıcınızda otomatik olarak açacaktır.

## Veri Yönetimi

### Yedekleme
Uygulama içindeki "Ayarlar" sekmesine gidin. "Veri Yönetimi" bölümündeki "Veritabanı Yedeği Al" düğmesine tıklayarak `database.sqlite` dosyasının bir kopyasını bilgisayarınıza indirebilirsiniz.

### Geri Yükleme
"Veritabanı Geri Yükle" düğmesine tıklayın. Açılan yeni pencereden daha önce aldığınız bir `.sqlite` yedeğini seçin ve "Geri Yükle" düğmesine tıklayın. İşlem tamamlandığında, uygulama otomatik olarak yeniden başlayacaktır.
