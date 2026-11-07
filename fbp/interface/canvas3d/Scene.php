<?php
namespace canvas3d;

/** Declarative scene. Coordinates: millimetres, right handed, Y up; angles: degrees. */
interface Scene {
    public function add(string $type, string $id, array $options = []): Scene;
    public function add_group(string $id, array $options = []): Scene;
    public function add_box(string $id, array $options = []): Scene;
    public function add_sphere(string $id, array $options = []): Scene;
    public function add_cylinder(string $id, array $options = []): Scene;
    public function add_cone(string $id, array $options = []): Scene;
    public function add_plane(string $id, array $options = []): Scene;
    public function add_circle(string $id, array $options = []): Scene;
    public function add_torus(string $id, array $options = []): Scene;
    public function add_rounded_box(string $id, array $options = []): Scene;
    public function add_extrude(string $id, array $options = []): Scene;
    public function add_part(string $part_id, string $id, array $options = []): Scene;
    public function set_render(array $options): Scene;
    public function set_lighting(array $options): Scene;
    public function set_view(string $name, array $options): Scene;
    public function set_background(string $color): Scene;
    public function animate(string $id, array $options): Scene;
    public function to_array(): array;
}
