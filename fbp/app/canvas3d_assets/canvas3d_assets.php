<?php
/** Public, non-sensitive framework textures. Never accepts saved model IDs or file paths. */
class canvas3d_assets {
    public function __construct(Controller $ctl) {$ctl->set_check_login(false);}
    public function texture(Controller $ctl): void {
        $id=$ctl->GET('id');$map=$ctl->GET('map')??'color';
        $ctl->res_3dcanvas_texture(is_string($id)?$id:'',is_string($map)?$map:'');
    }
}
