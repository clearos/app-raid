
Name: app-raid
Epoch: 1
Version: 0.9.0
Release: 1%{dist}
Summary: RAID Manager
License: GPLv3
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base

%description
RAID Tools provides status information and administrative actions for software RAID and supported hardware RAID controllers.

%package core
Summary: RAID Manager - APIs and install
License: LGPLv3
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-mail-notification-core
Requires: app-tasks-core
Requires: mdadm

%description core
RAID Tools provides status information and administrative actions for software RAID and supported hardware RAID controllers.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/raid
cp -r * %{buildroot}/usr/clearos/apps/raid/

install -D -m 0644 packaging/raid.conf %{buildroot}/etc/clearos/raid.conf

%post
logger -p local6.notice -t installer 'app-raid - installing'

%post core
logger -p local6.notice -t installer 'app-raid-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/raid/deploy/install ] && /usr/clearos/apps/raid/deploy/install
fi

[ -x /usr/clearos/apps/raid/deploy/upgrade ] && /usr/clearos/apps/raid/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-raid - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-raid-core - uninstalling'
    [ -x /usr/clearos/apps/raid/deploy/uninstall ] && /usr/clearos/apps/raid/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/raid/controllers
/usr/clearos/apps/raid/htdocs
/usr/clearos/apps/raid/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/raid/packaging
%exclude /usr/clearos/apps/raid/tests
%dir /usr/clearos/apps/raid
/usr/clearos/apps/raid/deploy
/usr/clearos/apps/raid/language
/usr/clearos/apps/raid/libraries
%config(noreplace) /etc/clearos/raid.conf
